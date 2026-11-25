<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Pricing\CountryPriceTable;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\CountryService;
use App\Shared\Money\Money;

/**
 * Price list of a central product (ADM-010b): one row per allowed country × variant.
 * Shopper price = cost basis + fixed shipping of that country (weight × rate), computed
 * by Commerce\Pricing\CountryPriceTable with no vendor margin (tenants add theirs).
 */
class ProductPriceListService
{
    public function __construct(private readonly CountryService $countries) {}

    /**
     * @return list<array{country: Country, variant: string|null, sku: string, base: Money, shipping: Money, shopper: Money, weight: int}>
     */
    public function rows(Product $product): array
    {
        $allowed = $product->allowed_country_ids;

        if ($allowed === []) {
            return [];
        }

        $countries = $this->countries->active($allowed === null ? null : array_map('intval', $allowed));
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
                    'shipping' => $shipping[(string) $country->id],
                    'shopper' => $table['prices'][(string) $country->id],
                    'weight' => $variant?->effectiveWeightGrams() ?? (int) ($product->weight_grams ?? 0),
                ];
            }
        }

        return $rows;
    }
}
