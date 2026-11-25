<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Vendor sourcing request ("order a new product") states, as the old project.
 */
enum ProductRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case InProduction = 'in_production';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
