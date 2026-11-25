<?php

namespace App\Modules\Commerce\Pricing;

use App\Shared\Money\Money;

/**
 * The order figures the refund maths read (snapshot columns of a store order) plus what
 * is already refunded or reserved by open refunds.
 */
final readonly class RefundableOrder
{
    public function __construct(
        public Money $grandTotal,
        public Money $itemsGrandTotal,
        public Money $couponDiscount,
        public Money $shippingCharge,
        public Money $alreadyRefunded,
        public int $lineCount = 1,
    ) {}

    /**
     * grand_total − refunded / reserved, never negative.
     */
    public function remaining(): Money
    {
        return $this->grandTotal->subtract($this->alreadyRefunded)->max(Money::zero($this->grandTotal->currency));
    }
}
