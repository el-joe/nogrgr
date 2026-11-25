<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Status filter of the vendor product lists.
 */
enum StoreProductFilter: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Featured = 'featured';
    case OutOfStock = 'out_of_stock';
}
