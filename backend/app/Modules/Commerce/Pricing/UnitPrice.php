<?php

namespace App\Modules\Commerce\Pricing;

use App\Shared\Money\Money;
use JsonSerializable;

/**
 * Result of UnitPricing::unitPrice(): price = cost + profit_amount (per unit);
 * fixed_shipping is reported separately and is charged once per order line.
 */
final readonly class UnitPrice implements JsonSerializable
{
    public function __construct(
        public Money $cost,
        public Money $profitAmount,
        public Money $price,
        public Money $fixedShipping,
    ) {}

    /**
     * Customer-facing price of one unit on its own line: price + fixed shipping.
     */
    public function displayPrice(): Money
    {
        return $this->price->add($this->fixedShipping);
    }

    /**
     * @return array{cost: Money, profit_amount: Money, price: Money, fixed_shipping: Money}
     */
    public function jsonSerialize(): array
    {
        return [
            'cost' => $this->cost,
            'profit_amount' => $this->profitAmount,
            'price' => $this->price,
            'fixed_shipping' => $this->fixedShipping,
        ];
    }
}
