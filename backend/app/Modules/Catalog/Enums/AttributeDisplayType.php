<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How an attribute's options are rendered on the storefront.
 */
enum AttributeDisplayType: string
{
    use HasEnumHelpers;

    case Text = 'text';
    case Color = 'color';
    case Image = 'image';
}
