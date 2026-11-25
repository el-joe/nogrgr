<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\PaymentLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A captured platform payment was refunded.
 *
 * Affiliate reversal hook (docs/plan/decisions.md): the Affiliates module has no
 * conversions ledger yet, so the refund is published as this event (plus
 * AffiliateConversionReversalRequested). The Affiliates module listens and reverses
 * the conversion tied to `paymentLog->id` once its ledger ships.
 */
final class PaymentRefunded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentLog $paymentLog) {}
}
