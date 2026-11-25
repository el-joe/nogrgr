<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Central product lifecycle; only published products reach tenants.
 */
enum ProductStatus: string
{
    use HasEnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
