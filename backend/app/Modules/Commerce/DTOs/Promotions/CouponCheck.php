<?php

namespace App\Modules\Commerce\DTOs\Promotions;

use App\Shared\Money\Money;
use Carbon\CarbonImmutable;

/**
 * The cart a coupon is checked against: items_grand_total (the min-spend basis and the
 * coupon's discount base), shipping charge, country and customer.
 */
final readonly class CouponCheck
{
    public function __construct(
        public Money $itemsGrandTotal,
        public ?int $countryId,
        public ?int $customerId = null,
        public bool $firstOrder = false,
        public ?Money $shippingCharge = null,
        public ?CarbonImmutable $at = null,
    ) {}
}
