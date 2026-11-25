<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Origin of a product row read through CatalogReader: the central catalog or a vendor-own product.
 */
enum CatalogSource: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Own = 'own';
}
