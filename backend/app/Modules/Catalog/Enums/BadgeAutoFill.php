<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How a badge list is filled: by hand only, or refreshed daily (and on save) with the
 * newest published products or the best sellers by units sold.
 */
enum BadgeAutoFill: string
{
    use HasEnumHelpers;

    case Manual = 'manual';
    case Newest = 'newest';
    case TopSelling = 'top_selling';

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }
}
