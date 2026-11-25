<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How an attribute's options are rendered on the storefront: text chips or colour
 * swatches (each option then carries a #RRGGBB swatch).
 */
enum AttributeDisplayType: string
{
    use HasEnumHelpers;

    case Text = 'text';
    case Color = 'color';

    public function usesSwatch(): bool
    {
        return $this === self::Color;
    }
}
