<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\ProfitType;
use App\Shared\Money\Money;

/**
 * Pure unit-price maths, ported from the old App\Support\OrderPricing::unitPrice()
 * (ORDER_FORMULAS.md §1). The full order maths (item/order/coupon/refund) is added in
 * P5; nothing outside Commerce\Pricing may re-implement these formulas.
 *
 *   profit_amount = fixed ? money(value) : money(cost × value / 100)
 *   price         = money(cost + profit_amount)
 *   fixed_shipping (per order line, NOT × qty) is passed through.
 */
final class UnitPricing
{
    public static function unitPrice(Money $cost, ProfitRule $profit, ?Money $fixedShipping = null): UnitPrice
    {
        $profitAmount = self::profitAmount($cost, $profit);

        return new UnitPrice(
            cost: $cost,
            profitAmount: $profitAmount,
            price: $cost->add($profitAmount),
            fixedShipping: $fixedShipping ?? Money::zero($cost->currency),
        );
    }

    public static function profitAmount(Money $cost, ProfitRule $profit): Money
    {
        return $profit->type === ProfitType::Fixed
            ? Money::of($profit->value, $cost->currency)
            : $cost->percentage($profit->value);
    }

    /**
     * Own (vendor-created) products: cost 0, profit = the vendor's sell price, no fixed shipping.
     */
    public static function ownProduct(Money $sellPrice): UnitPrice
    {
        $zero = Money::zero($sellPrice->currency);

        return new UnitPrice($zero, $sellPrice, $sellPrice, $zero);
    }
}
