<?php

namespace App\Modules\Storefront\Support;

/**
 * The six Elora home variants as configuration (decision #79): one home view renders the
 * section list of the store's variant; each section names a shared Elora section style.
 * Colours / backgrounds come from the variant tokens ([data-theme-variant] in
 * tokens/storefront-elora.css), so there are no per-variant view copies.
 *
 *   v1 base      1:15432 / m 1:21125     v2 Theme #1 1:26559 / m 1:24137
 *   v3 Theme #2  1:26978 / m 1:24477     v4 Theme #3 1:27464 / m 1:24870
 *   v5 Theme #4  1:28050 / m 1:25467     v6 Theme #5 1:28992 / m 1:25994
 *
 * Section types: hero (contained|full), features (dark|marquee), tabs, categories
 * (circles|tiles|cutout|boxed|slanted), flash (band|fan|mixed|grid|tiles), rail (data key +
 * heading style), category-band, shop-categories (cards|mosaic|side|portrait|small),
 * spotlight (center|left), wide (wide-card rail / grid), promo-rail, badges, recommended.
 */
final class EloraVariants
{
    public const COUNT = 6;

    /**
     * @return list<array<string, string>>
     */
    public static function sections(int $variant): array
    {
        if ($variant <= 1 || $variant > self::COUNT) {
            return [
                ['type' => 'hero', 'style' => 'contained'],
                ['type' => 'features', 'style' => 'dark'],
                ['type' => 'tabs'],
                ['type' => 'categories', 'style' => 'circles'],
                ['type' => 'flash', 'style' => 'band'],
                ['type' => 'rail', 'data' => 'trending', 'title' => 'elora.home.trending', 'heading' => 'center', 'icon' => 'flame', 'more' => 'storefront.best-sellers'],
                ['type' => 'category-band'],
                ['type' => 'rail', 'data' => 'best', 'title' => 'elora.home.best_seller', 'heading' => 'split', 'icon' => 'award', 'more' => 'storefront.best-sellers'],
                ['type' => 'rail', 'data' => 'newRail', 'title' => 'elora.home.new_arrivals', 'heading' => 'split', 'icon' => 'falling-star', 'more' => 'storefront.new-in'],
                ['type' => 'shop-categories', 'style' => 'cards'],
                ['type' => 'rail', 'data' => 'recommended', 'title' => 'elora.home.recommended', 'heading' => 'center-plain', 'icon' => 'thumbs-up', 'more' => 'storefront.new-in'],
                ['type' => 'badges'],
                ['type' => 'promo-rail'],
            ];
        }

        return [
            ['type' => 'hero', 'style' => 'full'],
            ['type' => 'features', 'style' => 'marquee'],
            ['type' => 'categories', 'style' => [2 => 'tiles', 3 => 'cutout', 4 => 'boxed', 5 => 'slanted', 6 => 'cutout'][$variant]],
            ['type' => 'flash', 'style' => [2 => 'fan', 3 => 'mixed', 4 => 'grid', 5 => 'tiles', 6 => 'grid'][$variant]],
            $variant === 3 || $variant === 5 || $variant === 6
                ? ['type' => 'wide', 'data' => 'newRail', 'title' => 'elora.home.new_in', 'band' => 'new', 'rows' => $variant === 5 ? '3' : ($variant === 6 ? 'mixed' : '1'), 'more' => 'storefront.new-in']
                : ['type' => 'rail', 'data' => 'newRail', 'title' => 'elora.home.new_in', 'heading' => 'start', 'band' => 'new', 'more' => 'storefront.new-in'],
            $variant === 5
                ? ['type' => 'rail', 'data' => 'trending', 'title' => 'elora.home.trending', 'heading' => 'start', 'more' => 'storefront.best-sellers']
                : ['type' => 'wide', 'data' => 'trending', 'title' => 'elora.home.trending', 'rows' => $variant === 3 ? '2' : '1', 'more' => 'storefront.best-sellers'],
            ['type' => 'shop-categories', 'style' => [2 => 'mosaic', 3 => 'mosaic', 4 => 'side', 5 => 'portrait', 6 => 'small'][$variant]],
            $variant === 5
                ? ['type' => 'rail', 'data' => 'best', 'title' => 'elora.home.best_seller', 'heading' => 'center-light', 'band' => 'best', 'more' => 'storefront.best-sellers']
                : ['type' => 'spotlight', 'data' => 'best', 'title' => 'elora.home.best_seller', 'layout' => $variant === 3 ? 'left' : 'center', 'more' => 'storefront.best-sellers'],
            ['type' => 'badges'],
            ['type' => 'recommended'],
        ];
    }
}
