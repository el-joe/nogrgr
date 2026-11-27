<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a page-builder product section takes its products from (VEN-032, decision #117 b):
 * the theme's own data (default), a store category, a product badge, products the vendor
 * picked by hand (in that order), the newest products or the best sellers.
 */
enum SectionProductSource: string
{
    use HasEnumHelpers;

    case Default = 'default';
    case Category = 'category';
    case Badge = 'badge';
    case Manual = 'manual';
    case NewIn = 'new';
    case BestSelling = 'best_selling';

    public static function translationKey(): string
    {
        return 'content::page_builder.sources';
    }
}
