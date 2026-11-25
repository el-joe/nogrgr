<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Badge lists; each feeds a storefront home section. Add a case to add a badge (BadgeService::ensureBadges upserts the rows).
 */
enum BadgeType: string
{
    use HasEnumHelpers;

    case NewIn = 'new_in';
    case BestSelling = 'best_selling';
    case Featured = 'featured';
    case Recommended = 'recommended';
    case TrendingNow = 'trending_now';
}
