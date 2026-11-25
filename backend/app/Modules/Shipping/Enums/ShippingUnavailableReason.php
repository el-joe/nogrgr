<?php

namespace App\Modules\Shipping\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why a shipping quote is unavailable (old PlacesOrders messages):
 * no destination country chosen, no active rule for the country, or no zone of
 * that rule serves the destination city.
 */
enum ShippingUnavailableReason: string
{
    use HasEnumHelpers;

    case CountryRequired = 'country_required';
    case CountryNotServed = 'country_not_served';
    case NoZone = 'no_zone';

    public function message(): string
    {
        return __('shipping::quote.unavailable.'.$this->value);
    }
}
