<?php

namespace App\Modules\Inventory\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Stock alerts of the store team (VEN-087 topic "low_stock"): crossing the product /
 * store threshold, or reaching zero.
 */
enum StockAlertEvent: string
{
    use HasEnumHelpers;

    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    /**
     * The alert of a stock move, if any: out of stock wins when the stock reaches zero;
     * low stock only when it crosses the threshold downwards.
     */
    public static function forChange(int $previous, int $current, int $threshold): ?self
    {
        return match (true) {
            $current <= 0 && $previous > 0 => self::OutOfStock,
            $current > 0 && $threshold > 0 && $current <= $threshold && $previous > $threshold => self::LowStock,
            default => null,
        };
    }

    /**
     * The stock came back from zero (back-in-stock subscribers).
     */
    public static function isRestock(int $previous, int $current): bool
    {
        return $previous <= 0 && $current > 0;
    }
}
