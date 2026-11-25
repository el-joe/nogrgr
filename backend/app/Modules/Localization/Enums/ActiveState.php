<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Active / inactive filter and list tabs for localization lists.
 */
enum ActiveState: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
}
