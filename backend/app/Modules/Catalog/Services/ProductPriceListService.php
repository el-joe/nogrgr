<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Pricing\CountryPriceTable;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Shipping\Services\FixedShippingCostService;
use App\Shared\Money\Money;

/**
 * Price list of a central product (ADM-010b): one row per active country × variant
 * (every product sells in every country, #115).
 * Shopper price = cost basis + fixed shipping of that country (weight × rate), computed
 * by Commerce\Pricing\CountryPriceTable with no vendor margin (tenants add theirs). The fixed
 * shipping comes from the precomputed fixed_shipping_costs map (Shipping recomputes it); `rate`
 * is the country's USD-per-gram rate (Figma "Ship/g" column), null when none is configured.
 */
class ProductPriceListService
{
    public function __construct(
        private readonly CountryService $countries,
        private readonly FixedShippingCostService $fixedShipping,
    ) {}

    /**
     * @return list<array{country: Country, variant: string|null, sku: string, base: Money, rate: string|null, shipping: Money, shopper: Money, weight: int}>
     */
    public function rows(Product $product): array
    {
        $countries = $this->countries->active(null);
        $rates = $this->fixedShipping->ratesByCountry();
        $targets = $product->variants()->with('options')->where('status', 'active')->get();
        $rows = [];

        foreach ($targets->isEmpty() ? [null] : $targets->all() as $variant) {
            /** @var ProductVariant|null $variant */
            $base = $variant?->costBasis() ?? $product->costBasis();
            $shipping = [];

            foreach ($countries as $country) {
                $fixed = $variant !== null ? $variant->fixedShippingFor($country->id) : $product->fixedShippingFor($country->id);
                $shipping[(string) $country->id] = $fixed ?? Money::zero();
            }

            $table = CountryPriceTable::forCatalog($base, $shipping, null, 0.0, $countries->pluck('id')->all());

            foreach ($countries as $country) {
                $rows[] = [
                    'country' => $country,
                    'variant' => $variant?->label(),
                    'sku' => $variant->sku ?? $product->sku,
                    'base' => $base,
                    'rate' => $rates[$country->id] ?? null,
                    'shipping' => $shipping[(string) $country->id],
                    'shopper' => $table['prices'][(string) $country->id],
                    'weight' => $variant?->effectiveWeightGrams() ?? (int) ($product->weight_grams ?? 0),
                ];
            }
        }

        return $rows;
    }
}
