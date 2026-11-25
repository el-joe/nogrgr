<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Services\SubscriptionRenewalService;

/**
 * A captured renewal payment (purpose renewal, subscription id on the log) renews
 * the subscription. Synchronous so the owner's return page already shows the new
 * period; idempotent for webhook replays.
 */
final class RenewSubscriptionOnPayment
{
    public function __construct(private readonly SubscriptionRenewalService $renewals) {}

    public function handle(PaymentCaptured $event): void
    {
        $this->renewals->applyCaptured($event->paymentLog);
    }
}
