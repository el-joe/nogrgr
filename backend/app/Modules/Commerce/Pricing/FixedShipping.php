<?php

namespace App\Modules\Commerce\Pricing;

use App\Shared\Money\Money;
use InvalidArgumentException;

/**
 * Fixed ("weight") shipping of a catalog line: weight_g × price_per_gram (USD),
 * rounded like the old SyncProductFixedShippingCosts (round(rate × weight, 2)).
 * Charged once per order line, never multiplied by the quantity.
 */
final class FixedShipping
{
    /**
     * @param  string|float  $pricePerGram  USD per gram (decimal(14,6) column value)
     */
    public static function forWeight(int $weightGrams, string|float $pricePerGram, string $currency = Money::BASE_CURRENCY): Money
    {
        if (is_string($pricePerGram) && ! is_numeric($pricePerGram)) {
            throw new InvalidArgumentException("Invalid price per gram [{$pricePerGram}].");
        }

        if ($weightGrams <= 0) {
            return Money::zero($currency);
        }

        return Money::of((float) $pricePerGram * $weightGrams, $currency);
    }

    /**
     * Precomputed map {country_id: minor units} for one weight, given the active rates
     * {country_id: price_per_gram}. No weight (or no rates) yields null = no fixed cost.
     *
     * @param  array<int|string, string|float>  $ratesByCountry
     * @return array<string, int>|null
     */
    public static function costMap(?int $weightGrams, array $ratesByCountry): ?array
    {
        if ($weightGrams === null || $weightGrams <= 0 || $ratesByCountry === []) {
            return null;
        }

        $map = [];

        foreach ($ratesByCountry as $countryId => $rate) {
            $map[(string) $countryId] = self::forWeight($weightGrams, $rate)->minor;
        }

        return $map;
    }
}
