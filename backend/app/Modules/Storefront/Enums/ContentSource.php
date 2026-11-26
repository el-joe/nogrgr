<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Origin of a merged storefront item (decision #65 b): the platform (central DB) or the
 * store itself (tenant DB).
 */
enum ContentSource: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Vendor = 'vendor';

    public static function translationKey(): string
    {
        return 'engagement::enums.content_source';
    }
}
