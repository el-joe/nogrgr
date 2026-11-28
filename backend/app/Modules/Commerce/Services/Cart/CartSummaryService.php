<?php

namespace App\Modules\Commerce\Services\Cart;

use App\Modules\Commerce\DTOs\Cart\CartLine;
use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\DTOs\Promotions\CouponCheck;
use App\Modules\Commerce\DTOs\Promotions\CouponDecision;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Pricing\OrderComposer;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Services\Promotions\CouponEligibilityService;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\ShippingQuoteService;

/**
 * Cart summary = resolved lines → OrderComposer (the only place the money is computed):
 *   1. compose without a coupon (shipping quote of the catalog lines);
 *   2. check the cart's coupon against that items_grand_total / shipping
 *      (CouponEligibilityService) and, when accepted, compose again with it on the same quote;
 *   3. delivery estimate (DeliveryEstimateService) when catalog lines ship;
 *   4. free-shipping threshold of the destination (grams).
 */
class CartSummaryService
{
    public function __construct(
        private readonly CartLineResolver $lines,
        private readonly OrderComposer $composer,
        private readonly CouponEligibilityService $coupons,
        private readonly DeliveryEstimateService $delivery,
        private readonly ShippingQuoteService $shipping,
        private readonly OrderRepositoryContract $orders,
    ) {}

    /**
     * @param  array<string, int>  $quantityOverrides
     */
    public function summarize(?Cart $cart, ?int $countryId, ?int $customerId, ?string $couponCode = null, array $quantityOverrides = [], ?int $cityId = null, ?int $priceCountryId = null): CartSummary
    {
        $cart?->loadMissing('items.storeProduct');
        $lines = $cart !== null ? $this->lines->resolve($cart->items, $countryId, $quantityOverrides, $priceCountryId) : [];
        $inputs = array_values(array_filter(array_map(static fn (CartLine $line): ?OrderLineInput => $line->isPurchasable() ? $line->input : null, $lines)));
        $composed = $this->composer->compose($inputs, $countryId, null, $cityId);
        $couponCode ??= $cart?->coupon_code;
        $decision = null;

        if ($couponCode !== null && $couponCode !== '' && ! $composed->isEmpty()) {
            $decision = $this->coupons->check($couponCode, new CouponCheck(
                itemsGrandTotal: $composed->itemsGrandTotal(),
                countryId: $countryId,
                customerId: $customerId,
                firstOrder: $customerId !== null && $this->orders->countForCustomer($customerId) === 0,
                shippingCharge: $composed->shippingCharge(),
            ));

            if ($decision->accepted() && $decision->coupon !== null) {
                $composed = OrderComposer::composeWithQuote($inputs, $composed->shipping, $decision->coupon->applied());
            }
        } elseif ($couponCode !== null && $couponCode !== '') {
            $decision = new CouponDecision(null);
        }

        $ships = $composed->shipping->available && array_filter($inputs, static fn (OrderLineInput $input): bool => ! $input->own && $input->shippable) !== [];

        return new CartSummary(
            $cart,
            $lines,
            $composed,
            $countryId,
            $decision,
            $ships && $countryId !== null ? $this->delivery->estimateFor($countryId) : null,
            $countryId !== null ? $this->shipping->freeShippingThreshold($countryId, $cityId) : null,
        );
    }
}
