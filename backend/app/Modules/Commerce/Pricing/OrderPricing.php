<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Shared\Money\Money;

/**
 * Pure order maths: the ONLY implementation of ORDER_FORMULAS.md §1–3 (items, order
 * totals, platform / vendor split, coupon value, return value). Ported from the old
 * App\Support\OrderPricing on integer USD cents; every rounding is Money's round2
 * (half-up to cents, float-noise tolerant), so results match the old system exactly.
 * No models, no DB.
 */
final class OrderPricing
{
    /**
     * One order line:
     *   price = cost + profit_amount; sub_total = price × qty;
     *   discount = money((sub_total + fixed_shipping) × flash_pct / 100), 0 without a sale;
     *   grand_total = sub_total + fixed_shipping − discount + tax + shipping_fee.
     */
    public static function item(OrderLineInput $line, ?Money $shippingFee = null): PricedLine
    {
        $shippingFee ??= Money::zero();
        $price = $line->cost->add($line->profitAmount);
        $subTotal = $price->multiply($line->quantity);
        $discount = $line->flashSalePercentage > 0
            ? $subTotal->add($line->fixedShipping)->percentage($line->flashSalePercentage)
            : Money::zero();

        return new PricedLine(
            key: $line->key,
            own: $line->own,
            quantity: $line->quantity,
            cost: $line->cost,
            profitAmount: $line->profitAmount,
            price: $price,
            subTotal: $subTotal,
            fixedShipping: $line->fixedShipping,
            discount: $discount,
            discountSource: $discount->isPositive() ? $line->flashSaleSource : null,
            tax: $line->tax,
            shippingFee: $shippingFee,
            grandTotal: $subTotal->add($line->fixedShipping, $line->tax, $shippingFee)->subtract($discount),
            weightGrams: $line->own ? 0 : max(0, $line->weightGrams) * $line->quantity,
        );
    }

    /**
     * Order totals and the platform / vendor split. A coupon without a known owner is
     * borne by the vendor (old rule); a zero coupon has no source.
     *
     * @param  list<PricedLine>  $lines
     */
    public static function order(array $lines, Money $shippingCharge, ?Money $couponDiscount = null, ?DiscountSource $couponSource = null): OrderTotals
    {
        $zero = Money::zero();
        $itemsGrand = $zero;
        $tax = $zero;
        $platform = $zero;
        $vendor = $zero;

        foreach ($lines as $line) {
            $itemsGrand = $itemsGrand->add($line->grandTotal);
            $tax = $tax->add($line->tax);
            $platform = $platform->add($line->platformShare());
            $vendor = $vendor->add($line->vendorShare());
        }

        $coupon = $couponDiscount !== null && $couponDiscount->isPositive() ? $couponDiscount : $zero;
        $source = $coupon->isPositive() ? ($couponSource ?? DiscountSource::Vendor) : null;

        $platform = $platform->add($shippingCharge, $tax);

        if ($source === DiscountSource::Central) {
            $platform = $platform->subtract($coupon);
        } elseif ($source === DiscountSource::Vendor) {
            $vendor = $vendor->subtract($coupon);
        }

        return new OrderTotals(
            lines: array_values($lines),
            itemsGrandTotal: $itemsGrand,
            tax: $tax,
            shippingCharge: $shippingCharge,
            couponDiscount: $coupon,
            couponSource: $source,
            grandTotal: $itemsGrand->add($shippingCharge)->subtract($coupon),
            platformProfit: $platform,
            vendorProfit: $vendor,
        );
    }

    /**
     * Coupon value for a cart:
     *   percentage    → money(items_grand_total × value / 100), capped by the max discount when set;
     *   fixed         → min(value, items_grand_total);
     *   free shipping → the order's shipping charge.
     * Never negative.
     */
    public static function couponDiscount(
        CouponType $type,
        Money $itemsGrandTotal,
        float $percentage = 0.0,
        ?Money $amount = null,
        ?Money $maxDiscount = null,
        ?Money $shippingCharge = null,
    ): Money {
        $zero = Money::zero($itemsGrandTotal->currency);

        $discount = match ($type) {
            CouponType::Percentage => $itemsGrandTotal->percentage(max(0.0, $percentage)),
            CouponType::Fixed => ($amount ?? $zero)->min($itemsGrandTotal),
            CouponType::FreeShipping => $shippingCharge ?? $zero,
        };

        if ($type === CouponType::Percentage && $maxDiscount !== null && $maxDiscount->isPositive()) {
            $discount = $discount->min($maxDiscount);
        }

        return $discount->max($zero);
    }

    /**
     * Refundable value of $qty of a line's $itemQty units: the line grand_total minus its
     * share of the order coupon (prorated by items_grand_total), × qty / itemQty.
     */
    public static function returnLineValue(Money $itemGrandTotal, Money $itemsGrandTotal, Money $couponDiscount, int $qty, int $itemQty): Money
    {
        $itemQty = max(1, $itemQty);
        $grand = $itemGrandTotal->toFloat();
        $couponShare = ($itemsGrandTotal->isPositive() && $couponDiscount->isPositive())
            ? $couponDiscount->toFloat() * $grand / $itemsGrandTotal->toFloat()
            : 0.0;

        return Money::of(max(0.0, Money::round2(($grand - $couponShare) * $qty / $itemQty)), $itemGrandTotal->currency);
    }

    /**
     * Splits an order coupon over the catalog and own orders of a mixed cart in
     * proportion to their items_grand_total: catalog = money(total × catalog / all),
     * own = total − catalog.
     *
     * @return array{0: Money, 1: Money} [catalog share, own share]
     */
    public static function splitCoupon(Money $couponTotal, Money $catalogItems, Money $allItems): array
    {
        if (! $allItems->isPositive() || ! $couponTotal->isPositive()) {
            return [Money::zero(), Money::zero()];
        }

        $catalog = Money::of($couponTotal->toFloat() * $catalogItems->toFloat() / $allItems->toFloat());

        return [$catalog, $couponTotal->subtract($catalog)];
    }
}
