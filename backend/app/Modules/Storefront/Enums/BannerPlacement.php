<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a banner shows on the storefront: the home hero slider or the home promo strip.
 * Labels: engagement::enums.banner_placement (the Storefront module ships no lang, #45).
 */
enum BannerPlacement: string
{
    use HasEnumHelpers;

    case HomeHero = 'home.hero';
    case HomePromo = 'home.promo';

    public static function translationKey(): string
    {
        return 'engagement::enums.banner_placement';
    }
}
