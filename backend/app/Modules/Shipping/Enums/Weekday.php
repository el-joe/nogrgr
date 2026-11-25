<?php

namespace App\Modules\Shipping\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Day of week, numbered like Carbon::dayOfWeek (0 = Sunday … 6 = Saturday).
 */
enum Weekday: int
{
    use HasEnumHelpers;

    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
}
