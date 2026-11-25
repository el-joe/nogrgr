<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\DTOs\ShippingQuoteRequest;
use App\Shared\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Data of the vendor "Shipping details" modal (VEN-010e, Figma 12:16200), pre-wired for
 * the vendor UI: per destination country, the fixed shipping added to the product's
 * display price (precomputed map, per line), the zone fee for one unit's weight
 * (default zone of the country's rule) and the delivery window.
 */
class ProductShippingDetailsService
{
    public function __construct(
        private readonly ShippingQuoteService $quotes,
        private readonly DeliveryEstimateService $estimates,
    ) {}

    /**
     * @param  Collection<int, Country>  $countries
     * @return list<array{country_id: int, country: string, iso2: string, weight_grams: int, fixed_shipping: Money, zone_fee: Money, available: bool, message: string|null, zone: string|null, band: string|null, min_date: string, max_date: string}>
     */
    public function forProduct(Product $product, ?ProductVariant $variant, Collection $countries, ?CarbonInterface $orderedAt = null): array
    {
        $weight = $variant?->effectiveWeightGrams() ?? (int) ($product->weight_grams ?? 0);
        $rows = [];

        foreach ($countries as $country) {
            $fixed = ($variant !== null ? $variant->fixedShippingFor($country->id) : $product->fixedShippingFor($country->id)) ?? Money::zero();
            $quote = $this->quotes->quote(ShippingQuoteRequest::forWeight($country->id, $weight));
            $estimate = $this->estimates->estimateFor($country->id, null, $orderedAt);

            $rows[] = [
                'country_id' => $country->id,
                'country' => (string) $country->name,
                'iso2' => $country->iso2,
                'weight_grams' => $weight,
                'fixed_shipping' => $fixed,
                'zone_fee' => $quote->amount,
                'available' => $quote->available,
                'message' => $quote->message(),
                'zone' => $quote->zoneName,
                'band' => $quote->bandName,
                'min_date' => $estimate->minDate->toDateString(),
                'max_date' => $estimate->maxDate->toDateString(),
            ];
        }

        return $rows;
    }
}
