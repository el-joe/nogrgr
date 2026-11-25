<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Outcome of inspecting a returned item: accepted (refund / exchange follows) or failed
 * (the return is rejected).
 */
enum InspectionResult: string
{
    use HasEnumHelpers;

    case Accepted = 'accepted';
    case Failed = 'failed';
}
