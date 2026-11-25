<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\CollectedBy;
use App\Shared\Money\Money;

/**
 * Settlement maths of an order (ORDER_FORMULAS.md §3, old OrderProfitCalculator):
 *   void (cancelled / refunded / fully refunded) → every figure 0;
 *   platform share = platform_profit; vendor share = vendor_profit − refunded (a partial
 *   refund is borne by the vendor only);
 *   vendor_own_platform = max(0, platform share − Σ paid settlements)  (collected by vendor);
 *   platform_own_vendor = max(0, vendor share)                          (collected by platform);
 *   a platform-collected order whose refunds exceed the vendor share (seller-fault return)
 *   leaves the store owing the shortfall: vendor_own_platform = max(0, −vendor share −
 *   Σ paid settlements) (decision #72, QA-P5-011). Payouts are tracked by the ledger.
 * Pay platform splits the amount due for display only:
 *   shipping_cost = min(shipping_charge, due), product_cost = due − shipping_cost.
 */
final class OrderSettlement
{
    public static function figures(
        Money $grandTotal,
        Money $platformProfit,
        Money $vendorProfit,
        Money $refunded,
        Money $settlementsPaid,
        CollectedBy $collectedBy,
        bool $voidStatus,
    ): SettlementFigures {
        $zero = Money::zero();
        $void = $voidStatus || ($refunded->isPositive() && ! $refunded->lessThan($grandTotal));
        $platform = $void ? $zero : $platformProfit;
        $vendor = $void ? $zero : $vendorProfit->subtract($refunded);
        $vendorPercent = $grandTotal->isPositive() ? (int) round($vendorProfit->minor * 100 / $grandTotal->minor) : 0;

        return new SettlementFigures(
            collectedBy: $collectedBy,
            void: $void,
            platformShare: $platform,
            vendorShare: $vendor,
            refunded: $refunded,
            settlementsPaid: $settlementsPaid,
            owedToPlatform: ($collectedBy === CollectedBy::Vendor ? $platform : $vendor->negate()->max($zero))->subtract($settlementsPaid)->max($zero),
            owedToVendor: $collectedBy === CollectedBy::Platform ? $vendor->max($zero) : $zero,
            vendorPercent: $vendorPercent,
            platformPercent: $grandTotal->isPositive() ? 100 - $vendorPercent : 0,
        );
    }

    /**
     * @return array{shipping: Money, product: Money}
     */
    public static function breakdown(Money $due, Money $shippingCharge): array
    {
        $shipping = $shippingCharge->min($due)->max(Money::zero());

        return ['shipping' => $shipping, 'product' => $due->subtract($shipping)];
    }
}
