<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Stock filter of the admin products list: in stock (above the minimum), low (at or
 * below `min_stock`) or out of stock (0).
 */
enum StockState: string
{
    use HasEnumHelpers;

    case InStock = 'in_stock';
    case Low = 'low';
    case OutOfStock = 'out_of_stock';
}
