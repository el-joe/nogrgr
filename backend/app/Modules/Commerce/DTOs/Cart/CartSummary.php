<?php

namespace App\Modules\Commerce\DTOs\Cart;

use App\Modules\Commerce\DTOs\Promotions\CouponDecision;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Pricing\ComposedOrder;
use App\Modules\Commerce\Pricing\OrderLineInput;
use App\Modules\Shipping\DTOs\DeliveryEstimate;
use LogicException;

/**
 * The cart as the storefront / app shows it: resolved lines, the OrderComposer result
 * (flash discounts, coupon, shipping quote, totals, split), the coupon verdict, delivery
 * estimate and free-shipping progress (grams).
 */
final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public ?Cart $cart,
        public array $lines,
        public ComposedOrder $composed,
        public ?int $countryId,
        public ?CouponDecision $coupon,
        public ?DeliveryEstimate $delivery,
        public ?int $freeShippingThreshold,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function hasIssues(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line->isPurchasable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<CartLine>
     */
    public function purchasable(): array
    {
        return array_values(array_filter($this->lines, static fn (CartLine $line): bool => $line->isPurchasable()));
    }

    /**
     * @return list<OrderLineInput>
     */
    public function inputs(): array
    {
        return array_values(array_map(static fn (CartLine $line): OrderLineInput => $line->input ?? throw new LogicException('Line has no input.'), $this->purchasable()));
    }

    public function line(string $key): ?CartLine
    {
        foreach ($this->lines as $line) {
            if ($line->key() === $key) {
                return $line;
            }
        }

        return null;
    }

    public function couponRejected(): bool
    {
        return $this->coupon !== null && ! $this->coupon->accepted();
    }

    public function weightGrams(): int
    {
        return $this->composed->shipping->totalWeightGrams;
    }
}
