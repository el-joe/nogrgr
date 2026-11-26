<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Operating system of a mobile app install (customer_devices.platform).
 */
enum DevicePlatform: string
{
    use HasEnumHelpers;

    case Ios = 'ios';
    case Android = 'android';
}
