<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Shipping\DTOs\ShippingQuote;
use App\Shared\Money\Money;

/**
 * Result of OrderComposer for one cart: the catalog order (primary, carries the shipping
 * charge) and, for a mixed cart, the merchant-fulfilled own-products companion order
 * (shipping 0). An own-only cart is a single primary order. The shipping quote says
 * whether the destination can be served.
 */
final readonly class ComposedOrder
{
    public function __construct(
        public ?OrderTotals $primary,
        public ?OrderTotals $companion,
        public ShippingQuote $shipping,
        public ?AppliedCoupon $coupon = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->primary === null;
    }

    public function isMixed(): bool
    {
        return $this->companion !== null;
    }

    public function canCheckout(): bool
    {
        return ! $this->isEmpty() && $this->shipping->available;
    }

    /**
     * @return list<OrderTotals>
     */
    public function orders(): array
    {
        return array_values(array_filter([$this->primary, $this->companion]));
    }

    public function grandTotal(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->grandTotal);
    }

    public function itemsGrandTotal(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->itemsGrandTotal);
    }

    public function couponDiscount(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->couponDiscount);
    }

    public function shippingCharge(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->shippingCharge);
    }

    public function platformProfit(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->platformProfit);
    }

    public function vendorProfit(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->vendorProfit);
    }

    public function flashDiscount(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->flashDiscount());
    }

    public function displaySubtotal(): Money
    {
        return $this->sum(static fn (OrderTotals $order): Money => $order->displaySubtotal());
    }

    /**
     * @param  callable(OrderTotals): Money  $pick
     */
    private function sum(callable $pick): Money
    {
        return Money::zero()->add(...array_map($pick, $this->orders()));
    }
}
