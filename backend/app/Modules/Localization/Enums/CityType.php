<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Seeded city kind; capitals are listed first in suggestions.
 */
enum CityType: string
{
    use HasEnumHelpers;

    case Capital = 'capital';
    case City = 'city';
}
