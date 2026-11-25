<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\DTOs\Promotions\CouponCheck;
use App\Modules\Commerce\DTOs\Promotions\CouponDecision;
use App\Modules\Commerce\DTOs\Promotions\PromotionCoupon;
use App\Modules\Commerce\Enums\CouponRejection;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Coupon rules for a cart (storefront, checkout, mobile): one coupon per order, applied
 * on top of the flash-sale prices (its base is items_grand_total, ORDER_FORMULAS.md).
 * Checks, in order: exists → active → started → not ended → country → first order →
 * per-customer limit → total usage limit → minimum spend (on items_grand_total) →
 * something to discount. Central usage limits count every store's redemptions.
 */
class CouponEligibilityService
{
    public function __construct(
        private readonly CouponCatalog $coupons,
        private readonly CouponRepositoryContract $central,
        private readonly StoreCouponRepositoryContract $store,
    ) {}

    public function check(string $code, CouponCheck $cart): CouponDecision
    {
        $coupon = $this->coupons->findByCode($code);

        return $coupon === null ? new CouponDecision(null, CouponRejection::NotFound) : $this->checkCoupon($coupon, $cart);
    }

    public function checkCoupon(PromotionCoupon $coupon, CouponCheck $cart): CouponDecision
    {
        $at = $cart->at ?? CarbonImmutable::now();
        $reject = static fn (CouponRejection $reason): CouponDecision => new CouponDecision($coupon, $reason);

        if (! $coupon->active) {
            return $reject(CouponRejection::Inactive);
        }

        if ($coupon->startsAt !== null && $coupon->startsAt->greaterThan($at)) {
            return $reject(CouponRejection::NotStarted);
        }

        if ($coupon->endsAt !== null && $coupon->endsAt->lessThan($at)) {
            return $reject(CouponRejection::Expired);
        }

        if (! $coupon->appliesToCountry($cart->countryId)) {
            return $reject(CouponRejection::CountryNotAllowed);
        }

        if (($coupon->firstOrderOnly || $coupon->perCustomerLimit !== null) && $cart->customerId === null) {
            return $reject(CouponRejection::SignInRequired);
        }

        if ($coupon->firstOrderOnly && ! $cart->firstOrder) {
            return $reject(CouponRejection::FirstOrderOnly);
        }

        if ($coupon->perCustomerLimit !== null && $cart->customerId !== null && $this->customerUsage($coupon, $cart->customerId) >= $coupon->perCustomerLimit) {
            return $reject(CouponRejection::CustomerLimitReached);
        }

        if ($coupon->usageLimit !== null && $this->usage($coupon) >= $coupon->usageLimit) {
            return $reject(CouponRejection::UsageLimitReached);
        }

        if ($cart->itemsGrandTotal->lessThan($coupon->minSpend)) {
            return $reject(CouponRejection::MinimumSpend);
        }

        $discount = $coupon->applied()->discountFor($cart->itemsGrandTotal, $cart->shippingCharge ?? Money::zero());

        if (! $discount->isPositive() && ($coupon->type !== CouponType::FreeShipping || $cart->shippingCharge !== null)) {
            return $reject(CouponRejection::NothingToDiscount);
        }

        return new CouponDecision($coupon, null, $discount);
    }

    /**
     * Redemptions of the coupon (central: across every store).
     */
    public function usage(PromotionCoupon $coupon): int
    {
        return $coupon->isCentral() ? $this->central->usageCount($coupon->id) : $this->store->usageCount($coupon->id);
    }

    /**
     * Records one use by an order (idempotent per order reference).
     */
    public function recordRedemption(PromotionCoupon $coupon, ?int $customerId, string $orderReference, Money $discount): void
    {
        if ($coupon->isCentral()) {
            $this->central->recordRedemption($coupon->id, (int) StoreContext::tenant()->getTenantKey(), $customerId, $orderReference, $discount->minor);

            return;
        }

        $this->store->recordRedemption($coupon->id, $customerId, $orderReference, $discount->minor);
    }

    private function customerUsage(PromotionCoupon $coupon, int $customerId): int
    {
        return $coupon->isCentral()
            ? $this->central->customerUsageCount($coupon->id, (int) StoreContext::tenant()->getTenantKey(), $customerId)
            : $this->store->customerUsageCount($coupon->id, $customerId);
    }
}
