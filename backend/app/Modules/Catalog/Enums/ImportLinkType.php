<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Kind of local record an external id is linked to by the importer.
 */
enum ImportLinkType: string
{
    use HasEnumHelpers;

    case Product = 'product';
    case Variant = 'variant';
    case Media = 'media';
}
