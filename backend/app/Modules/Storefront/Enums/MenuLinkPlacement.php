<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a store menu link shows (Appearance › Header links VEN-034d / Footer VEN-034e):
 * the store bar above every theme header, or one of the footer link columns.
 */
enum MenuLinkPlacement: string
{
    use HasEnumHelpers;

    case Header = 'header';
    case Footer = 'footer';

    /**
     * Labels live in the Content module's panel translations: the Storefront module ships no
     * lang directory (decision #45, StorefrontStaticTextsTest).
     */
    public static function translationKey(): string
    {
        return 'content::store_enums.menu_link_placement';
    }
}
