<?php

namespace App\Modules\Storefront\Support;

/**
 * The six Souqify home variants as configuration (same pattern as EloraVariants, decision
 * #79): one home view renders the section list of the store's variant; each entry names a
 * shared Souqify section style. Colours, bands and shapes come from the variant tokens
 * ([data-theme-variant] in tokens/storefront-souqify.css), so there are no per-variant views.
 *
 *   v1 base      1:72835 / m 1:77852     v2 Theme #1 1:81585 / m 1:78202
 *   v3 Theme #2  1:82288 / m 1:78599     v4 Theme #3 1:82796 / m 1:78957
 *   v5 Theme #4  1:83157 / m 1:79266     v6 Theme #5 1:83936 / m 1:79691
 *
 * Section types: hero (deck = slider + two side banners | split = full-width banner with the
 * copy at the start | center = dark banner, centred copy), features (icons | marquee),
 * categories (squares | arch | pinwheel | dots | outline), flash (deck | rail | stack | fan |
 * cards | spotlight), rail (data key, optional band), promos (two promo banners), side (deal
 * side-card rows), featured (grid + deal panel), wide (wide-card rows), shop-categories
 * (mosaic | slanted | arch | dots | outline), best (groups | rail | spotlight | fan | stagger),
 * badges, recommended (rails | grid).
 */
final class SouqifyVariants
{
    public const COUNT = 6;

    /**
     * @return list<array<string, string>>
     */
    public static function sections(int $variant): array
    {
        if ($variant <= 1 || $variant > self::COUNT) {
            return [
                ['type' => 'hero', 'style' => 'deck'],
                ['type' => 'features', 'style' => 'icons'],
                ['type' => 'rail', 'data' => 'trending', 'title' => 'souqify.home.trending_week', 'more' => 'storefront.best-sellers'],
                ['type' => 'categories', 'style' => 'squares'],
                ['type' => 'flash', 'style' => 'deck'],
                ['type' => 'rail', 'data' => 'best', 'title' => 'souqify.home.top_products', 'more' => 'storefront.best-sellers'],
                ['type' => 'promos'],
                ['type' => 'rail', 'data' => 'newRail', 'title' => 'souqify.home.new_arrivals', 'band' => 'new', 'more' => 'storefront.new-in'],
                ['type' => 'side', 'data' => 'offers', 'title' => 'souqify.home.explore', 'more' => 'storefront.offers'],
                ['type' => 'featured', 'data' => 'topRated', 'title' => 'souqify.home.featured', 'more' => 'storefront.top-rated'],
                ['type' => 'rail', 'data' => 'deals', 'title' => 'souqify.home.hot_deals', 'more' => 'storefront.offers'],
                ['type' => 'rail', 'data' => 'offers', 'title' => 'souqify.home.special_offers', 'band' => 'offers', 'more' => 'storefront.offers'],
                ['type' => 'rail', 'data' => 'topRated', 'title' => 'souqify.home.top_rated', 'more' => 'storefront.top-rated'],
                ['type' => 'badges'],
                ['type' => 'recommended', 'style' => 'rails'],
            ];
        }

        return [
            ['type' => 'hero', 'style' => $variant === 3 ? 'center' : 'split'],
            ['type' => 'features', 'style' => 'marquee'],
            ['type' => 'categories', 'style' => [2 => 'cutout', 3 => 'pinwheel', 4 => 'arch', 5 => 'dots', 6 => 'outline'][$variant]],
            ['type' => 'flash', 'style' => [2 => 'rail', 3 => 'stack', 4 => 'fan', 5 => 'cards', 6 => 'spotlight'][$variant]],
            in_array($variant, [2, 5], true)
                ? ['type' => 'wide', 'data' => 'newRail', 'title' => 'souqify.home.new_in', 'rows' => '3', 'band' => 'new', 'more' => 'storefront.new-in']
                : ['type' => 'rail', 'data' => 'newRail', 'title' => 'souqify.home.new_in', 'band' => $variant === 3 ? null : 'new', 'more' => 'storefront.new-in'],
            in_array($variant, [2, 5], true)
                ? ['type' => 'rail', 'data' => 'trending', 'title' => 'souqify.home.trending_now', 'more' => 'storefront.best-sellers']
                : ['type' => 'wide', 'data' => 'trending', 'title' => 'souqify.home.trending_now', 'rows' => '2', 'progress' => $variant === 4 ? '1' : '', 'more' => 'storefront.best-sellers'],
            ['type' => 'shop-categories', 'style' => [2 => 'mosaic', 3 => 'slanted', 4 => 'arch', 5 => 'dots', 6 => 'outline'][$variant]],
            ['type' => 'best', 'style' => [2 => 'groups', 3 => 'rail', 4 => 'spotlight', 5 => 'fan', 6 => 'stagger'][$variant], 'data' => 'best', 'more' => 'storefront.best-sellers'],
            ['type' => 'badges'],
            ['type' => 'recommended', 'style' => 'grid'],
        ];
    }
}
