<?php

namespace App\Modules\Commerce\Pricing;

use App\Shared\Money\Money;
use InvalidArgumentException;

/**
 * Pure refundable-amount maths (ORDER_FORMULAS.md §3 "Return refund", old
 * RefundCalculator). Integer USD cents, rounding through OrderPricing / Money::round2.
 *
 *   cancellation: items = items_grand_total − coupon_discount, shipping = shipping_charge,
 *                 amount = remaining (grand_total − refunded); when partly refunded the
 *                 shipping is refunded first and the rest counts as goods.
 *   return:       items = OrderPricing::returnLineValue(line, qty) (line grand_total less
 *                 its prorated coupon share × qty / line qty);
 *                 shipping = shipping_charge only for a seller-fault reason when the
 *                 whole order comes back; fee = restocking fee (bps of the items amount),
 *                 waived for seller-fault reasons;
 *                 amount = min(max(0, items + shipping − fee), remaining).
 *   manual:       any amount 0 < x ≤ remaining.
 */
final class RefundCalculator
{
    public static function forCancellation(RefundableOrder $order): RefundBreakdown
    {
        $items = $order->itemsGrandTotal->subtract($order->couponDiscount)->max(Money::zero());
        $shipping = $order->shippingCharge;
        $remaining = $order->remaining();

        if ($remaining->lessThan($items->add($shipping))) {
            $shipping = $shipping->min($remaining);
            $items = $remaining->subtract($shipping);
        }

        return new RefundBreakdown($items, $shipping, Money::zero(), $remaining, $remaining);
    }

    /**
     * @param  bool|null  $wholeOrder  this return brings back the whole order (null = the
     *                                 order has one line and every unit comes back)
     */
    public static function forReturn(
        RefundableOrder $order,
        Money $lineGrandTotal,
        int $lineQuantity,
        int $quantity,
        bool $sellerFault,
        int $restockingFeeBps = 0,
        ?bool $wholeOrder = null,
    ): RefundBreakdown {
        $lineQuantity = max(1, $lineQuantity);

        if ($quantity < 1 || $quantity > $lineQuantity) {
            throw new InvalidArgumentException("Return quantity must be between 1 and {$lineQuantity}.");
        }

        $items = OrderPricing::returnLineValue($lineGrandTotal, $order->itemsGrandTotal, $order->couponDiscount, $quantity, $lineQuantity);
        $wholeOrder ??= $order->lineCount <= 1 && $quantity >= $lineQuantity;
        $shipping = $sellerFault && $wholeOrder ? $order->shippingCharge : Money::zero();
        $policyFee = $restockingFeeBps > 0 ? $items->percentage($restockingFeeBps / 100) : Money::zero();
        $fee = $sellerFault ? Money::zero() : $policyFee;
        $gross = $items->add($shipping)->subtract($fee)->max(Money::zero());
        $remaining = $order->remaining();
        $amount = $gross->min($remaining);

        return new RefundBreakdown($items, $shipping, $fee, $amount, $remaining, $amount->lessThan($gross), $sellerFault && $policyFee->isPositive());
    }

    /**
     * Staff refund of a chosen amount: valid when 0 < amount ≤ remaining.
     */
    public static function manual(RefundableOrder $order, Money $amount): RefundBreakdown
    {
        $remaining = $order->remaining();

        if (! $amount->isPositive() || $amount->greaterThan($remaining)) {
            throw new InvalidArgumentException('Refund amount must be between 0.01 and the refundable amount.');
        }

        return new RefundBreakdown($amount, Money::zero(), Money::zero(), $amount, $remaining);
    }
}
