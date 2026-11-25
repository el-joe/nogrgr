<?php

namespace App\Modules\Commerce\Pricing;

use App\Shared\Money\Money;

/**
 * Result of RefundCalculator: what the refund is made of and the amount to pay back.
 */
final readonly class RefundBreakdown
{
    public function __construct(
        public Money $itemsAmount,
        public Money $shippingAmount,
        public Money $returnFee,
        public Money $amount,
        public Money $remaining,
        public bool $capped = false,
        public bool $feeWaived = false,
    ) {}
}
