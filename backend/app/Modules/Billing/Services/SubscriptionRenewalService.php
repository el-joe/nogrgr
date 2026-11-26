<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\StartPaymentData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Modules\Identity\Models\Owner;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Payments\PaymentDriverRegistry;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Support\Collection;

/**
 * Paid renewal / plan change through a hosted checkout (Stripe / PayPal…) of
 * GatewayPaymentService, started from the owner portal (OWN-003) or the vendor panel
 * (VEN-060a). The payment log carries the subscription id (renewal) or the plan id +
 * term (plan change). Once the payment is captured (return URL or webhook, whichever is
 * first) RenewSubscriptionOnPayment renews the subscription for one more term, or starts
 * the new plan, and marks its invoice paid. Replay-safe (renewed_at on the payload).
 */
class SubscriptionRenewalService extends BaseService
{
    public function __construct(
        private readonly GatewayPaymentService $payments,
        private readonly PaymentDriverRegistry $drivers,
        private readonly PaymentLogRepositoryContract $logs,
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly SubscriptionService $subscriptionService,
        private readonly PlanRepositoryContract $plans,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Gateways offered for a renewal (same set as the paid sign-up).
     *
     * @return Collection<int, PaymentGateway>
     */
    public function gateways(): Collection
    {
        return $this->drivers->checkoutGateways(GatewayPurpose::TenantRegistration);
    }

    public function renewable(Subscription $subscription): bool
    {
        return $subscription->status->isOpen() && $subscription->amount_minor > 0;
    }

    /**
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws BillingRuleException
     * @throws PaymentGatewayException
     */
    public function start(Subscription $subscription, PaymentGatewayCode $gateway, Owner $owner, Closure $returnUrls): array
    {
        return $this->startRenewal($subscription, $gateway, $owner->email, $owner->locale ?? app()->getLocale(), ['owner_id' => $owner->id], $returnUrls);
    }

    /**
     * Renew the open subscription for one more term (any payer: owner or store staff).
     *
     * @param  array<string, int|string|null>  $meta
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws BillingRuleException
     * @throws PaymentGatewayException
     */
    public function startRenewal(Subscription $subscription, PaymentGatewayCode $gateway, ?string $email, ?string $locale, array $meta, Closure $returnUrls): array
    {
        if (! $this->renewable($subscription)) {
            throw BillingRuleException::invalidTransition($subscription->status->label(), __('billing::owner.subscriptions.renew'));
        }

        $subscription->loadMissing('plan');

        return $this->payments->start(new StartPaymentData(
            gateway: $gateway,
            purpose: PaymentPurpose::Renewal,
            amount: $subscription->amount(),
            description: __('billing::owner.subscriptions.payment_description', [
                'plan' => (string) $subscription->plan?->name,
                'term' => $subscription->term->label(),
                'app' => config('app.name'),
            ]),
            customerEmail: $email,
            locale: $locale,
            tenantId: $subscription->tenant_id,
            meta: [...$meta, 'subscription_id' => $subscription->id],
        ), $returnUrls);
    }

    /**
     * Subscribe / change plan (VEN-060a): pay the plan's price for the term now; the
     * capture starts the new subscription (the current one ends, as on an admin assign).
     *
     * @param  array<string, int|string|null>  $meta
     * @param  Closure(PaymentLog): array{success: string, cancel: string}  $returnUrls
     * @return array{log: PaymentLog, redirect: string}
     *
     * @throws BillingRuleException
     * @throws PaymentGatewayException
     */
    public function startChange(int $tenantId, Plan $plan, BillingTerm $term, PaymentGatewayCode $gateway, ?string $email, ?string $locale, array $meta, Closure $returnUrls): array
    {
        $price = $plan->priceFor($term);

        if (! $price->isPositive()) {
            throw new BillingRuleException(__('billing::vendor_subscription.errors.free_plan'));
        }

        return $this->payments->start(new StartPaymentData(
            gateway: $gateway,
            purpose: PaymentPurpose::Renewal,
            amount: $price,
            description: __('billing::owner.subscriptions.payment_description', [
                'plan' => $plan->name,
                'term' => $term->label(),
                'app' => config('app.name'),
            ]),
            customerEmail: $email,
            locale: $locale,
            tenantId: $tenantId,
            meta: [...$meta, 'plan_id' => $plan->id, 'term' => $term->value],
        ), $returnUrls);
    }

    /**
     * The renewal payment log of this subscription (and only this one).
     */
    public function logFor(Subscription $subscription, int $logId): ?PaymentLog
    {
        /** @var PaymentLog|null $log */
        $log = $this->logs->query()->withoutGlobalScopes()->whereKey($logId)->first();

        return $log !== null
            && $log->purpose === PaymentPurpose::Renewal
            && (int) (($log->payload ?? [])['subscription_id'] ?? 0) === $subscription->id ? $log : null;
    }

    /**
     * A renewal / plan-change payment log of the store (and only that store's).
     */
    public function logForTenant(int $tenantId, int $logId): ?PaymentLog
    {
        /** @var PaymentLog|null $log */
        $log = $this->logs->query()->withoutGlobalScopes()->whereKey($logId)->first();

        return $log !== null && $log->tenant_id === $tenantId && $log->purpose === PaymentPurpose::Renewal ? $log : null;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function confirm(PaymentLog $log): PaymentLog
    {
        return $this->payments->confirm($log);
    }

    public function cancel(PaymentLog $log): PaymentLog
    {
        return $this->payments->cancel($log);
    }

    /**
     * PaymentCaptured body: renews (or starts the paid plan) once per payment log —
     * webhook replays are ignored.
     */
    public function applyCaptured(PaymentLog $log): ?Subscription
    {
        $payload = $log->payload ?? [];

        if ($log->purpose !== PaymentPurpose::Renewal || isset($payload['renewed_at'])) {
            return null;
        }

        if (isset($payload['plan_id'])) {
            return $this->applyChange($log, $payload);
        }

        $subscriptionId = (int) ($payload['subscription_id'] ?? 0);

        if ($subscriptionId === 0) {
            return null;
        }

        /** @var Subscription|null $subscription */
        $subscription = $this->subscriptions->query()->withoutGlobalScopes()->whereKey($subscriptionId)->first();

        if ($subscription === null || ! $subscription->status->isOpen()) {
            return null;
        }

        return $this->transaction(function () use ($log, $subscription, $payload): Subscription {
            $this->subscriptions->update($subscription, ['gateway' => $log->gateway]);
            $this->subscriptionService->renew($subscription, markPaid: true);
            $this->logs->update($log, ['payload' => [...$payload, 'renewed_at' => now()->toIso8601String()]]);

            return $subscription;
        }, $subscription->getConnectionName());
    }

    /**
     * Plan change paid: the new subscription starts now (no trial) and its invoice is paid.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyChange(PaymentLog $log, array $payload): ?Subscription
    {
        /** @var Plan|null $plan */
        $plan = $this->plans->find((int) $payload['plan_id']);
        $term = BillingTerm::tryFrom((string) ($payload['term'] ?? ''));

        if ($plan === null || $term === null || $log->tenant_id === null) {
            return null;
        }

        return $this->transaction(function () use ($log, $plan, $term, $payload): Subscription {
            $subscription = $this->subscriptionService->subscribe((int) $log->tenant_id, $plan, $term, $log->gateway, withTrial: false);
            $invoice = $subscription->latestInvoice()->withoutGlobalScopes()->first();

            if ($invoice instanceof Invoice) {
                $this->invoices->markPaid($invoice, $log->gateway->value, $log->reference());
            }

            $this->logs->update($log, ['payload' => [...$payload, 'subscription_id' => $subscription->id, 'renewed_at' => now()->toIso8601String()]]);

            return $subscription;
        }, $plan->getConnectionName());
    }
}
