<?php

namespace App\Modules\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cross-module request: reverse the affiliate conversion earned on a refunded
 * platform payment (old system: affiliate_conversions.payment_log_id). Plain scalars
 * so the Affiliates module needs no Billing model to handle it.
 * Amounts are USD minor units.
 */
final class AffiliateConversionReversalRequested
{
    use Dispatchable;

    public function __construct(
        public readonly int $paymentLogId,
        public readonly ?int $tenantId,
        public readonly int $refundedAmountMinor,
        public readonly string $currency,
        public readonly ?string $reason = null,
    ) {}
}
