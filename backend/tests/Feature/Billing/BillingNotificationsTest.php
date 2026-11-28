<?php

use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Billing\Events\PaymentRefunded;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Events\SubscriptionExpired;
use App\Modules\Billing\Events\SubscriptionPastDue;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\BillingAlertNotification;
use App\Modules\Billing\Notifications\VendorBillingMail;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\NotificationPreferenceRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/BillingHelpers.php';

/*
| Billing events → notifications: admins with the matching permission get a panel
| notification (database + mail + Reverb per preferences), admins without it get
| nothing, and the store owner gets an e-mail (admin template when active).
*/

function billingNotificationTenant(): Tenant
{
    $tenant = billingTenant();
    $tenant->forceFill(['owner_name' => 'Mona Khaled', 'owner_email' => 'owner@megz.test'])->save();

    return $tenant;
}

it('notifies finance admins and the owner when an invoice is issued and paid', function (string $eventClass, string $name, EmailTemplateEvent $template): void {
    Notification::fake();
    $tenant = billingNotificationTenant();
    $finance = billingAdmin(['finance.invoices.view']);
    $other = billingAdmin(['plans.view']);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'number' => 'INV-2026-0007', 'bill_to_email' => 'billing@megz.test']);

    event(new $eventClass($invoice));

    Notification::assertSentTo($finance, BillingAlertNotification::class, function (BillingAlertNotification $notification, array $channels) use ($name, $invoice, $finance): bool {
        $data = $notification->toArray($finance);

        return $notification->event() === $name
            && $notification->category() === NotificationCategory::Payments
            && $channels === ['database', 'mail', 'broadcast']
            && $data['title_params']['number'] === 'INV-2026-0007'
            && $data['url'] === route('admin.finance.invoices.show', $invoice->id, false); // stored host-independent (QA-FINAL-008)
    });
    Notification::assertNotSentTo($other, BillingAlertNotification::class);
    Notification::assertSentOnDemand(VendorBillingMail::class, fn (VendorBillingMail $mail, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'billing@megz.test'
        && $mail->template === $template
        && $mail->variables['invoice.number'] === 'INV-2026-0007'
        && $mail->variables['store.name'] === 'Megz Store');
})->with([
    'issued' => [InvoiceIssued::class, 'invoice_issued', EmailTemplateEvent::VendorInvoiceIssued],
    'paid' => [InvoicePaid::class, 'invoice_paid', EmailTemplateEvent::VendorInvoicePaid],
]);

it('notifies payment admins about failed and refunded payments', function (string $eventClass, string $name): void {
    Notification::fake();
    $tenant = billingNotificationTenant();
    $admin = billingAdmin(['finance.payment-logs.view']);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
    $log = PaymentLog::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id]);

    event(new $eventClass($log));

    Notification::assertSentTo($admin, BillingAlertNotification::class, fn (BillingAlertNotification $notification): bool => $notification->event() === $name
        && $notification->toArray($admin)['url'] === route('admin.finance.payment-logs.show', $log->id, false));
    Notification::assertSentOnDemand(VendorBillingMail::class, fn (VendorBillingMail $mail, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'owner@megz.test'
        && $mail->variables['payment.gateway'] === 'Stripe');
})->with([
    'failed' => [PaymentFailed::class, 'payment_failed'],
    'refunded' => [PaymentRefunded::class, 'payment_refunded'],
]);

it('notifies subscription admins and the owner about subscription changes', function (string $eventClass, string $name): void {
    Notification::fake();
    $tenant = billingNotificationTenant();
    $admin = billingAdmin(['subscriptions.view']);
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    event(new $eventClass($subscription));

    Notification::assertSentTo($admin, BillingAlertNotification::class, fn (BillingAlertNotification $notification): bool => $notification->event() === $name
        && $notification->category() === NotificationCategory::Vendors
        && $notification->toArray($admin)['title_params']['store'] === 'Megz Store');
    Notification::assertSentOnDemand(VendorBillingMail::class, fn (VendorBillingMail $mail): bool => $mail->eventName === $name);
})->with([
    'activated' => [SubscriptionActivated::class, 'subscription_activated'],
    'past due' => [SubscriptionPastDue::class, 'subscription_past_due'],
    'expired' => [SubscriptionExpired::class, 'subscription_expired'],
]);

it('respects the admin category preferences', function (): void {
    Notification::fake();
    $tenant = billingNotificationTenant();
    $admin = billingAdmin(['finance.invoices.view']);
    $preferences = app(NotificationPreferenceRepositoryContract::class);
    $preferences->put($admin, NotificationCategory::Payments, NotificationChannel::Email, false);
    $preferences->put($admin, NotificationCategory::Payments, NotificationChannel::Push, false);

    event(new InvoicePaid(Invoice::factory()->create(['tenant_id' => $tenant->id])));

    Notification::assertSentTo($admin, BillingAlertNotification::class, fn (BillingAlertNotification $notification, array $channels): bool => $channels === ['database']);
});

it('stores the admin notification in the inbox and renders the owner mail', function (): void {
    $tenant = billingNotificationTenant();
    $admin = billingAdmin(['finance.invoices.view']);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'number' => 'INV-2026-0042', 'total_minor' => 4900]);

    event(new InvoiceIssued($invoice));

    $row = $admin->notifications()->sole();
    expect($row->type)->toBe('payments.invoice_issued')
        ->and(__($row->data['title_key'], $row->data['title_params']))->toBe('Invoice INV-2026-0042 issued');

    $fallback = (new VendorBillingMail(EmailTemplateEvent::VendorInvoiceIssued, 'invoice_issued', ['vendor.name' => 'Mona', 'invoice.number' => 'INV-2026-0042', 'invoice.total' => '$49.00', 'invoice.due_date' => '2026-12-01', 'store.name' => 'Megz Store'], 'en'))
        ->toMail(Notification::route('mail', 'owner@megz.test'));
    expect($fallback->subject)->toBe('New invoice INV-2026-0042 for Megz Store')
        ->and($fallback->viewData['html'])->toContain('INV-2026-0042'); // registry default (#124)

    EmailTemplate::factory()->event(EmailTemplateEvent::VendorInvoiceIssued)->create(['subject' => ['en' => 'Bill {{ invoice.number }}'], 'body' => ['en' => '<p>Hi {{ vendor.name }}</p>']]);
    $templated = (new VendorBillingMail(EmailTemplateEvent::VendorInvoiceIssued, 'invoice_issued', ['vendor.name' => 'Mona', 'invoice.number' => 'INV-2026-0042'], 'en'))
        ->toMail(Notification::route('mail', 'owner@megz.test'));

    expect($templated->subject)->toBe('Bill INV-2026-0042')
        ->and($templated->viewData['html'])->toContain('Hi Mona');
});
