<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\DiscountSource;
use App\Shared\Money\Money;

/**
 * Totals of one order and the platform / vendor split (ORDER_FORMULAS.md §1–2):
 *
 *   items_grand_total = Σ item grand_total
 *   grand_total       = items_grand_total + shipping_charge − coupon_discount
 *   platform_profit   = Σ(cost × qty + fixed − central flash discount) + shipping + tax − central coupon
 *   vendor_profit     = Σ(profit × qty − vendor flash discount) − vendor coupon
 *
 * Invariant: platform_profit + vendor_profit = grand_total.
 */
final readonly class OrderTotals
{
    /**
     * @param  list<PricedLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $itemsGrandTotal,
        public Money $tax,
        public Money $shippingCharge,
        public Money $couponDiscount,
        public ?DiscountSource $couponSource,
        public Money $grandTotal,
        public Money $platformProfit,
        public Money $vendorProfit,
    ) {}

    /**
     * Customer-facing items subtotal: Σ(sub_total + fixed_shipping).
     */
    public function displaySubtotal(): Money
    {
        return Money::zero()->add(...array_map(static fn (PricedLine $line): Money => $line->displaySubtotal(), $this->lines));
    }

    /**
     * Flash-sale discounts of all lines.
     */
    public function flashDiscount(): Money
    {
        return Money::zero()->add(...array_map(static fn (PricedLine $line): Money => $line->discount, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items_grand_total' => $this->itemsGrandTotal->minor,
            'tax' => $this->tax->minor,
            'shipping_charge' => $this->shippingCharge->minor,
            'coupon_discount' => $this->couponDiscount->minor,
            'coupon_source' => $this->couponSource?->value,
            'grand_total' => $this->grandTotal->minor,
            'platform_profit' => $this->platformProfit->minor,
            'vendor_profit' => $this->vendorProfit->minor,
            'lines' => array_map(static fn (PricedLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}
