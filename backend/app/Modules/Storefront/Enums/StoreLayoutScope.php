<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a page-builder layout belongs to (VEN-032): the store home (store default or one
 * selling country) or one custom store page (VEN-033).
 */
enum StoreLayoutScope: string
{
    use HasEnumHelpers;

    case Home = 'home';
    case Page = 'page';

    public static function translationKey(): string
    {
        return 'content::page_builder.scopes';
    }
}
