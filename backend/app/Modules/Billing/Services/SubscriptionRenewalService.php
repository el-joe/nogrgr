<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\StartPaymentData;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
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
 * Owner-initiated renewal (OWN-003): hosted checkout (Stripe / PayPal…) through
 * GatewayPaymentService with the subscription id on the payment log. Once the payment
 * is captured (return URL or webhook, whichever is first) RenewSubscriptionOnPayment
 * renews the subscription for one more term and marks the renewal invoice paid.
 */
class SubscriptionRenewalService extends BaseService
{
    public function __construct(
        private readonly GatewayPaymentService $payments,
        private readonly PaymentDriverRegistry $drivers,
        private readonly PaymentLogRepositoryContract $logs,
        private readonly SubscriptionRepositoryContract $subscriptions,
        private readonly SubscriptionService $subscriptionService,
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
            customerEmail: $owner->email,
            locale: $owner->locale ?? app()->getLocale(),
            tenantId: $subscription->tenant_id,
            meta: ['subscription_id' => $subscription->id, 'owner_id' => $owner->id],
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
     * PaymentCaptured body: renews once per payment log (webhook replays are ignored).
     */
    public function applyCaptured(PaymentLog $log): ?Subscription
    {
        $payload = $log->payload ?? [];
        $subscriptionId = (int) ($payload['subscription_id'] ?? 0);

        if ($log->purpose !== PaymentPurpose::Renewal || $subscriptionId === 0 || isset($payload['renewed_at'])) {
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
}
