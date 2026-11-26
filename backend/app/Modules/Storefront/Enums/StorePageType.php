<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A store CMS page seeded for every store (system: slug fixed, cannot be deleted) or one
 * the store added (custom).
 */
enum StorePageType: string
{
    use HasEnumHelpers;

    case System = 'system';
    case Custom = 'custom';

    public static function translationKey(): string
    {
        return 'engagement::enums.store_page_type';
    }
}
