<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Origin of a store product (tenant `store_products.source`): an imported central
 * catalog product or a product created by the vendor itself.
 */
enum StoreProductSource: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Own = 'own';
}
