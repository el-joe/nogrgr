<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Modules\Commerce\Pricing\ProfitRule;
use App\Shared\Money\Money;

/**
 * One line of a store price list: variant × country, priced by Commerce\Pricing
 * (price = cost + profit_amount + fixed shipping of that country).
 */
final readonly class StorePriceRow
{
    public function __construct(
        public ?int $variantId,
        public ?string $variantLabel,
        public int $countryId,
        public string $countryName,
        public string $iso2,
        public Money $cost,
        public Money $shipping,
        public ProfitRule $rule,
        public Money $profit,
        public Money $price,
    ) {}

    /**
     * Profit share of the shopper price, in percent (display only).
     */
    public function marginPercent(): float
    {
        return $this->price->isPositive() ? round($this->profit->minor / $this->price->minor * 100, 1) : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'variant' => $this->variantLabel,
            'country_id' => $this->countryId,
            'country' => $this->countryName,
            'iso2' => $this->iso2,
            'cost' => $this->cost->format(),
            'shipping' => $this->shipping->format(),
            'profit_type' => $this->rule->type->value,
            'profit_value' => $this->rule->value,
            'profit' => $this->profit->format(),
            'price' => $this->price->format(),
            'price_minor' => $this->price->minor,
            'margin' => $this->marginPercent(),
        ];
    }
}
