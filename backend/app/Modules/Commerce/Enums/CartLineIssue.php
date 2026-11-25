<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why a cart line cannot be bought right now (CartLineResolver). Lines with an issue are
 * shown but left out of the totals; checkout is refused while any exists.
 */
enum CartLineIssue: string
{
    use HasEnumHelpers;

    case Inactive = 'inactive';
    case VariantUnavailable = 'variant_unavailable';
    case NotSoldInCountry = 'not_sold_in_country';
    case OutOfStock = 'out_of_stock';
    case InsufficientStock = 'insufficient_stock';

    public function message(): string
    {
        return (string) __('commerce::cart.issues.'.$this->value);
    }
}
