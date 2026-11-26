<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Footer link column of a custom footer link (same columns as the store pages,
 * SystemStorePage::footerGroup(); headings = static text shell.footer.columns.<value>).
 */
enum FooterColumn: string
{
    use HasEnumHelpers;

    case Company = 'company';
    case Service = 'service';
    case Help = 'help';

    /**
     * Labels live in the Content module's panel translations: the Storefront module ships no
     * lang directory (decision #45, StorefrontStaticTextsTest).
     */
    public static function translationKey(): string
    {
        return 'content::store_enums.footer_column';
    }
}
