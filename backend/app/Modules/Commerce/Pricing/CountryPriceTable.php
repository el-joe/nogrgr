<?php

namespace App\Modules\Commerce\Pricing;

use App\Shared\Money\Money;

/**
 * Per-country catalog prices of one product/variant for one tenant, ported from the old
 * App\Services\Tenant\TenantPricingService. Keys are country ids as strings plus "default".
 *
 *   price[key] = base + profit_amount(key) + fixed_shipping[key]   (fixed shipping of "default" = 0)
 *
 * Existing vendor profit rows win; every active-country key missing a row is seeded with
 * the tenant's global profit percentage. Pure: callers pass the rates and keys.
 */
final class CountryPriceTable
{
    public const DEFAULT_KEY = 'default';

    /**
     * @param  array<string, mixed>|null  $existing  stored profit JSON
     * @param  list<string>  $keys  active country keys
     * @return array<array-key, ProfitRule>
     */
    public static function seedProfit(?array $existing, array $keys, float $tenantPercentage): array
    {
        $rows = [];

        foreach ((array) $existing as $key => $row) {
            if ($row instanceof ProfitRule) {
                $rows[(string) $key] = $row;
            } elseif (is_array($row)) {
                $rows[(string) $key] = ProfitRule::fromArray($row);
            }
        }

        foreach ([self::DEFAULT_KEY, ...$keys] as $key) {
            $rows[(string) $key] ??= ProfitRule::percentage($tenantPercentage);
        }

        return $rows;
    }

    /**
     * @param  array<array-key, ProfitRule>  $profit
     * @param  array<array-key, Money>  $shipping
     * @return array{prices: array<array-key, Money>, profit: array<array-key, array{profit_type: string, profit_value: float, total_profit: float}>}
     */
    public static function compute(Money $base, array $profit, array $shipping): array
    {
        $prices = [];
        $profitJson = [];

        foreach ($profit as $key => $rule) {
            $key = (string) $key;
            $unit = UnitPricing::unitPrice($base, $rule, $key === self::DEFAULT_KEY ? null : ($shipping[$key] ?? null));
            $prices[$key] = $unit->displayPrice();
            $profitJson[$key] = [
                'profit_type' => $rule->type->value,
                'profit_value' => $rule->value,
                'total_profit' => $unit->profitAmount->toFloat(),
            ];
        }

        return ['prices' => $prices, 'profit' => $profitJson];
    }

    /**
     * Full derivation used by the vendor price list, imports and the recompute jobs.
     *
     * @param  array<array-key, Money>  $shipping  fixed shipping per country key
     * @param  array<string, mixed>|null  $existingProfit
     * @param  list<string|int>  $activeCountryKeys  countries with an active fixed-shipping rate
     * @return array{prices: array<array-key, Money>, default: Money, profit: array<array-key, array{profit_type: string, profit_value: float, total_profit: float}>}
     */
    public static function forCatalog(
        Money $base,
        array $shipping,
        ?array $existingProfit,
        float $tenantPercentage,
        array $activeCountryKeys = [],
        ?Money $overrideDefault = null,
        ?float $overrideMarginPercentage = null,
    ): array {
        unset($shipping[self::DEFAULT_KEY]);

        $keys = array_values(array_diff(
            array_unique([...array_map('strval', $activeCountryKeys), ...array_map('strval', array_keys($shipping))]),
            [self::DEFAULT_KEY],
        ));

        $profit = self::seedProfit($existingProfit, $keys, $tenantPercentage);

        if ($overrideMarginPercentage !== null) {
            $profit[self::DEFAULT_KEY] = ProfitRule::percentage(Money::round2($overrideMarginPercentage));
        } elseif ($overrideDefault !== null) {
            $profit[self::DEFAULT_KEY] = ProfitRule::fixed(max(0.0, $overrideDefault->subtract($base)->toFloat()));
        }

        $result = self::compute($base, $profit, $shipping);

        return [
            'prices' => $result['prices'],
            'default' => $result['prices'][self::DEFAULT_KEY],
            'profit' => $result['profit'],
        ];
    }

    /**
     * The profit row that applies to a country: its own key, else "default", else the fallback %.
     *
     * @param  array<string, mixed>|null  $profit
     */
    public static function ruleFor(?array $profit, ?int $countryId, float $fallbackPercentage = 0.0): ProfitRule
    {
        $row = $countryId !== null ? ($profit[(string) $countryId] ?? null) : null;
        $row ??= $profit[self::DEFAULT_KEY] ?? null;

        return is_array($row) ? ProfitRule::fromArray($row) : ProfitRule::percentage($fallbackPercentage);
    }
}
