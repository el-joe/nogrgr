<?php

namespace App\Modules\Catalog\Enums;

/**
 * What happened to store products (StoreProductsChanged event, read by the storefront
 * cache in P7).
 */
enum StoreProductChange: string
{
    case Imported = 'imported';
    case Updated = 'updated';
    case Prices = 'prices';
    case Removed = 'removed';
    case Created = 'created';
}
