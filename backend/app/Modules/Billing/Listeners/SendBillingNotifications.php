<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Events\InvoiceVoided;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Billing\Events\PaymentRefunded;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Events\SubscriptionCancelled;
use App\Modules\Billing\Events\SubscriptionExpired;
use App\Modules\Billing\Events\SubscriptionPastDue;
use App\Modules\Billing\Events\SubscriptionRenewed;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\BillingAlertNotification;
use App\Modules\Billing\Notifications\VendorBillingMail;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

/**
 * Billing events → notifications (decision #46 follow-up): admins who can see the
 * record get a panel notification (database + mail + Reverb, per their category
 * preferences); the store owner gets an e-mail (admin template when active).
 */
class SendBillingNotifications
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(InvoiceIssued::class, [self::class, 'invoiceIssued']);
        $events->listen(InvoicePaid::class, [self::class, 'invoicePaid']);
        $events->listen(PaymentFailed::class, [self::class, 'paymentFailed']);
        $events->listen(PaymentRefunded::class, [self::class, 'paymentRefunded']);
        $events->listen(SubscriptionActivated::class, [self::class, 'subscriptionActivated']);
        $events->listen(SubscriptionPastDue::class, [self::class, 'subscriptionPastDue']);
        $events->listen(SubscriptionExpired::class, [self::class, 'subscriptionExpired']);
        $events->listen(SubscriptionCancelled::class, [self::class, 'subscriptionCancelled']);
        $events->listen(SubscriptionRenewed::class, [self::class, 'subscriptionRenewed']);
        $events->listen(InvoiceVoided::class, [self::class, 'invoiceVoided']);
    }

    public function invoiceIssued(InvoiceIssued $event): void
    {
        $this->invoice($event->invoice, 'invoice_issued', EmailTemplateEvent::VendorInvoiceIssued);
    }

    public function invoicePaid(InvoicePaid $event): void
    {
        $this->invoice($event->invoice, 'invoice_paid', EmailTemplateEvent::VendorInvoicePaid);
    }

    public function paymentFailed(PaymentFailed $event): void
    {
        $this->payment($event->paymentLog, 'payment_failed', EmailTemplateEvent::VendorPaymentFailed);
    }

    public function paymentRefunded(PaymentRefunded $event): void
    {
        $this->payment($event->paymentLog, 'payment_refunded', EmailTemplateEvent::VendorPaymentRefunded);
    }

    public function subscriptionActivated(SubscriptionActivated $event): void
    {
        $this->subscription($event->subscription, 'subscription_activated', EmailTemplateEvent::VendorSubscriptionActivated);
    }

    public function subscriptionPastDue(SubscriptionPastDue $event): void
    {
        $this->subscription($event->subscription, 'subscription_past_due', EmailTemplateEvent::VendorSubscriptionPastDue);
    }

    public function subscriptionExpired(SubscriptionExpired $event): void
    {
        $this->subscription($event->subscription, 'subscription_expired', EmailTemplateEvent::VendorSubscriptionExpired);
    }

    public function subscriptionCancelled(SubscriptionCancelled $event): void
    {
        $this->subscription($event->subscription, 'subscription_cancelled', EmailTemplateEvent::VendorSubscriptionCancelled);
    }

    public function subscriptionRenewed(SubscriptionRenewed $event): void
    {
        $this->subscription($event->subscription, 'subscription_renewed', EmailTemplateEvent::VendorSubscriptionRenewed);
    }

    public function invoiceVoided(InvoiceVoided $event): void
    {
        $this->invoice($event->invoice, 'invoice_voided', EmailTemplateEvent::VendorInvoiceVoided);
    }

    private function invoice(Invoice $invoice, string $name, EmailTemplateEvent $template): void
    {
        $tenant = $this->tenant($invoice->tenant_id);
        $store = $tenant->name ?? (string) $invoice->bill_to_name;
        $total = $invoice->total()->format();

        $this->notifyAdmins('finance.invoices.view', new BillingAlertNotification(
            NotificationCategory::Payments,
            $name,
            ['number' => $invoice->number, 'store' => $store, 'total' => $total],
            route('admin.finance.invoices.show', $invoice->id),
        ));

        $this->mailOwner($tenant, $invoice->bill_to_email, $template, $name, [
            'invoice.number' => (string) $invoice->number,
            'invoice.total' => $total,
            'invoice.due_date' => (string) $invoice->due_at?->toDateString(),
        ]);
    }

    private function payment(PaymentLog $log, string $name, EmailTemplateEvent $template): void
    {
        $tenant = $this->tenant($log->tenant_id);
        $amount = $log->amount()->format();
        $invoice = $log->invoice;

        $this->notifyAdmins('finance.payment-logs.view', new BillingAlertNotification(
            NotificationCategory::Payments,
            $name,
            ['reference' => $log->reference(), 'store' => $tenant->name ?? '—', 'amount' => $amount, 'gateway' => $log->gateway->label()],
            route('admin.finance.payment-logs.show', $log->id),
        ));

        $this->mailOwner($tenant, $invoice?->bill_to_email, $template, $name, [
            'invoice.number' => (string) $invoice?->number,
            'invoice.total' => (string) $invoice?->total()->format(),
            'payment.amount' => $amount,
            'payment.gateway' => $log->gateway->label(),
        ]);
    }

    private function subscription(Subscription $subscription, string $name, EmailTemplateEvent $template): void
    {
        $tenant = $this->tenant($subscription->tenant_id);
        $plan = (string) $subscription->plan?->name;
        $endsAt = (string) $subscription->current_period_ends_at?->toDateString();

        $this->notifyAdmins('subscriptions.view', new BillingAlertNotification(
            NotificationCategory::Vendors,
            $name,
            ['store' => $tenant->name ?? '—', 'plan' => $plan, 'date' => $endsAt],
            route('admin.billing.subscriptions.index'),
        ));

        $this->mailOwner($tenant, null, $template, $name, [
            'plan.name' => $plan,
            'subscription.ends_at' => $endsAt,
        ]);
    }

    private function notifyAdmins(string $permission, BillingAlertNotification $notification): void
    {
        $admins = $this->admins->activeWithPermission($permission);

        if ($admins->isNotEmpty()) {
            $this->dispatcher->send($admins, $notification);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function mailOwner(?Tenant $tenant, ?string $billingEmail, EmailTemplateEvent $template, string $name, array $variables): void
    {
        $email = $billingEmail ?: $tenant?->owner_email;

        if (blank($email)) {
            return;
        }

        Notification::route('mail', $email)->notify(new VendorBillingMail(
            $template,
            $name,
            [
                'vendor.name' => (string) ($tenant->owner_name ?? ''),
                'store.name' => (string) ($tenant->name ?? ''),
                ...$variables,
            ],
            (string) config('app.locale', 'en'),
            route('central.tenant.login'),
        ));
    }

    private function tenant(?int $tenantId): ?Tenant
    {
        $tenant = $tenantId !== null ? $this->tenants->find($tenantId) : null;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
