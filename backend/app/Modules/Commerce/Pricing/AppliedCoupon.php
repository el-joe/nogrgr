<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Shared\Money\Money;

/**
 * The coupon of an order as the engine needs it: its value, cap and owner. Built by the
 * promotions layer from a validated coupon (Commerce\Promotions\PromotionCoupon::applied()).
 */
final readonly class AppliedCoupon
{
    public function __construct(
        public CouponType $type,
        public DiscountSource $source,
        public float $percentage = 0.0,
        public ?Money $amount = null,
        public ?Money $maxDiscount = null,
        public string $code = '',
        public ?int $id = null,
    ) {}

    public static function percentage(float $percentage, DiscountSource $source, ?Money $maxDiscount = null, string $code = ''): self
    {
        return new self(CouponType::Percentage, $source, $percentage, null, $maxDiscount, $code);
    }

    public static function fixed(Money $amount, DiscountSource $source, string $code = ''): self
    {
        return new self(CouponType::Fixed, $source, 0.0, $amount, null, $code);
    }

    public static function freeShipping(DiscountSource $source, string $code = ''): self
    {
        return new self(CouponType::FreeShipping, $source, 0.0, null, null, $code);
    }

    /**
     * Coupon value for a cart (OrderPricing::couponDiscount).
     */
    public function discountFor(Money $itemsGrandTotal, Money $shippingCharge): Money
    {
        return OrderPricing::couponDiscount($this->type, $itemsGrandTotal, $this->percentage, $this->amount, $this->maxDiscount, $shippingCharge);
    }
}
