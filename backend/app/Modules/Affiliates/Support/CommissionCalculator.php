<?php

namespace App\Modules\Affiliates\Support;

use App\Modules\Affiliates\Enums\RateType;
use App\Shared\Money\Money;

/**
 * Commission / discount math on USD cents (integers only):
 *   percentage → round half up (amount × bps / 10 000)
 *   fixed      → min(value, amount) — never more than the sale itself
 * Negative or zero amounts give 0.
 */
final class CommissionCalculator
{
    public const BPS = 10000;

    public static function amount(RateType $type, int $value, int $amountMinor): int
    {
        if ($amountMinor <= 0 || $value <= 0) {
            return 0;
        }

        return match ($type) {
            RateType::Percentage => intdiv($amountMinor * min($value, self::BPS) + intdiv(self::BPS, 2), self::BPS),
            RateType::Fixed => min($value, $amountMinor),
        };
    }

    /**
     * Human label: "30%" / "$25.00".
     */
    public static function label(RateType $type, int $value): string
    {
        if ($type === RateType::Fixed) {
            return Money::ofMinor($value)->format();
        }

        return rtrim(rtrim(number_format($value / 100, 2, '.', ''), '0'), '.').'%';
    }
}
