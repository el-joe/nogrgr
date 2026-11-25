<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Storefront theme families (01-modules §A5): Ecommet ×1, Elora ×6, Souqify ×6.
 */
enum ThemeFamily: string
{
    use HasEnumHelpers;

    case Ecommet = 'ecommet';
    case Elora = 'elora';
    case Souqify = 'souqify';

    /**
     * Labels live with the central website copy (the storefront:: namespace holds no lang files, decision #57).
     */
    public static function translationKey(): string
    {
        return 'content::enums.theme_family';
    }
}
