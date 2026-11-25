<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a central product can be sold (`allowed_country_ids`, ORDER_FORMULAS):
 * all = NULL, none = [] (hidden everywhere), selected = [ids].
 */
enum CountryAvailability: string
{
    use HasEnumHelpers;

    case All = 'all';
    case Selected = 'selected';
    case None = 'none';

    /**
     * @param  list<int>|null  $ids
     */
    public static function fromIds(?array $ids): self
    {
        return match (true) {
            $ids === null => self::All,
            $ids === [] => self::None,
            default => self::Selected,
        };
    }

    /**
     * Value of the `allowed_country_ids` column.
     *
     * @param  list<int>  $selected
     * @return list<int>|null
     */
    public function toIds(array $selected): ?array
    {
        return match ($this) {
            self::All => null,
            self::None => [],
            self::Selected => array_values(array_unique(array_map('intval', $selected))),
        };
    }
}
