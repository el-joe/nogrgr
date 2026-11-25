<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Manufacturing request states, as the old project.
 */
enum ManufacturingRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case UnderReview = 'under_review';
    case InProduction = 'in_production';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
}
