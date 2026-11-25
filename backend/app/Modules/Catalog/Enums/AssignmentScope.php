<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Central product offered to every tenant, or only to the tenants assigned to it (tenant-owned).
 */
enum AssignmentScope: string
{
    use HasEnumHelpers;

    case All = 'all';
    case Selected = 'selected';
}
