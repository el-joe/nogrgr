<?php

namespace App\Modules\Platform\Legacy\Support;

use App\Shared\Money\Money;

/**
 * Small value conversions shared by the steps: decimal USD → integer minor units
 * (rounded exactly like the old OrderPricing::money(), via App\Shared\Money\Money),
 * percentages → basis points, JSON columns, booleans.
 */
final class LegacyValues
{
    public static function minor(mixed $decimal): int
    {
        if ($decimal === null || $decimal === '') {
            return 0;
        }

        return Money::of(is_numeric($decimal) ? (string) $decimal : '0')->minor;
    }

    public static function minorOrNull(mixed $decimal): ?int
    {
        return $decimal === null || $decimal === '' ? null : self::minor($decimal);
    }

    /**
     * True when the old decimal had sub-cent precision that rounding changed.
     */
    public static function losesPrecision(mixed $decimal): bool
    {
        if (! is_numeric($decimal)) {
            return false;
        }

        return abs(round((float) $decimal * 100) - (float) $decimal * 100) > 1e-6;
    }

    public static function bps(mixed $percent): int
    {
        return is_numeric($percent) ? max(0, (int) round((float) $percent * 100)) : 0;
    }

    /**
     * @return array<mixed>|null
     */
    public static function json(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<int>
     */
    public static function intList(mixed $value): array
    {
        $list = self::json($value) ?? [];

        return array_values(array_unique(array_map('intval', array_filter($list, 'is_numeric'))));
    }

    public static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
