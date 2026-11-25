<?php

namespace App\Modules\Commerce\DTOs\Promotions;

use App\Modules\Commerce\Enums\CouponRejection;
use App\Shared\Money\Money;

/**
 * Outcome of CouponEligibilityService::check(): the coupon and its discount, or why it
 * was refused (with a translated message).
 */
final readonly class CouponDecision
{
    public function __construct(
        public ?PromotionCoupon $coupon,
        public ?CouponRejection $rejection = null,
        public ?Money $discount = null,
    ) {}

    public function accepted(): bool
    {
        return $this->rejection === null && $this->coupon !== null;
    }

    public function message(): ?string
    {
        if ($this->rejection === null) {
            return null;
        }

        return __('commerce::coupons.rejections.'.$this->rejection->value, [
            'amount' => $this->coupon?->minSpend->format() ?? '',
        ]);
    }
}
