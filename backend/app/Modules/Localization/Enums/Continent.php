<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Continent of a country (UN M49 grouping; Central America and the Caribbean are North America).
 */
enum Continent: string
{
    use HasEnumHelpers;

    case Africa = 'africa';
    case Antarctica = 'antarctica';
    case Asia = 'asia';
    case Europe = 'europe';
    case NorthAmerica = 'north_america';
    case Oceania = 'oceania';
    case SouthAmerica = 'south_america';
}
