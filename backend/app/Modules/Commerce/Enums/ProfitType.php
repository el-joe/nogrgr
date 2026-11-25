<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How a vendor's profit on a catalog line is expressed (ORDER_FORMULAS.md §1):
 * a percentage of the cost, or a fixed USD amount per unit.
 */
enum ProfitType: string
{
    use HasEnumHelpers;

    case Percentage = 'percentage';
    case Fixed = 'fixed';

    /**
     * Legacy rule: anything that is not exactly "fixed" is a percentage.
     */
    public static function fromLegacy(mixed $value): self
    {
        return $value === self::Fixed->value || $value === self::Fixed ? self::Fixed : self::Percentage;
    }
}
