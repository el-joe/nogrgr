<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Brand request states, as the old project.
 */
enum BrandRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
}
