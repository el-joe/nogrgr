<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Tabs of the admin products list (ADM-010).
 */
enum ProductListTab: string
{
    use HasEnumHelpers;

    case All = 'all';
    case Published = 'published';
    case Draft = 'draft';
    case Archived = 'archived';
    case OutOfStock = 'out_of_stock';
    case Trash = 'trash';
}
