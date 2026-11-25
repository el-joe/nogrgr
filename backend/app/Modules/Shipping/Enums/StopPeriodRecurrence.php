<?php

namespace App\Modules\Shipping\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A stop period is a date range (holiday) or a weekday repeated every week
 * (from starts_on, until ends_on when set).
 */
enum StopPeriodRecurrence: string
{
    use HasEnumHelpers;

    case Once = 'once';
    case Weekly = 'weekly';
}
