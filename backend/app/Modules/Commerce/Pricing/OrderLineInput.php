<?php

namespace App\Modules\Commerce\Pricing;

use App\Modules\Commerce\Enums\DiscountSource;
use App\Shared\Money\Money;
use InvalidArgumentException;

/**
 * One cart line handed to the order engine: the unit split (cost / profit_amount from
 * UnitPricing), the per-line fixed shipping (NOT × qty), the best flash sale of the line
 * and its owner, and the unit weight for the shipping quote. Own (vendor-created) products
 * have cost 0, profit = sell price, no fixed shipping and do not ship (ORDER_FORMULAS.md §1).
 */
final readonly class OrderLineInput
{
    public int $quantity;

    public float $flashSalePercentage;

    public Money $tax;

    public function __construct(
        public string $key,
        public Money $cost,
        public Money $profitAmount,
        int $quantity,
        public Money $fixedShipping,
        float $flashSalePercentage = 0.0,
        public ?DiscountSource $flashSaleSource = null,
        ?Money $tax = null,
        public bool $own = false,
        public int $weightGrams = 0,
        public bool $shippable = true,
    ) {
        if ($cost->currency !== Money::BASE_CURRENCY || $profitAmount->currency !== Money::BASE_CURRENCY || $fixedShipping->currency !== Money::BASE_CURRENCY) {
            throw new InvalidArgumentException('Order lines are priced in the base currency (USD).');
        }

        $this->quantity = max(1, $quantity);
        $this->flashSalePercentage = min(100.0, max(0.0, $flashSalePercentage));
        $this->tax = $tax ?? Money::zero();
    }

    /**
     * A catalog (central product) line from its UnitPrice for the order's country.
     */
    public static function catalog(string $key, UnitPrice $unit, int $quantity, int $weightGrams = 0, float $flashSalePercentage = 0.0, ?DiscountSource $flashSaleSource = null, bool $shippable = true): self
    {
        return new self($key, $unit->cost, $unit->profitAmount, $quantity, $unit->fixedShipping, $flashSalePercentage, $flashSaleSource, null, false, $weightGrams, $shippable);
    }

    /**
     * An own-product line: priced by UnitPricing::ownProduct(), ships free (weight 0).
     */
    public static function own(string $key, Money $sellPrice, int $quantity, float $flashSalePercentage = 0.0, ?DiscountSource $flashSaleSource = null): self
    {
        $unit = UnitPricing::ownProduct($sellPrice);

        return new self($key, $unit->cost, $unit->profitAmount, $quantity, $unit->fixedShipping, $flashSalePercentage, $flashSaleSource, null, true, 0, false);
    }
}
