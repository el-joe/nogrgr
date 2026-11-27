<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Http\Resources\StoreApi\CatalogCards;
use App\Modules\Storefront\Services\Layouts\HomeSectionHydrator;
use Illuminate\Http\Request;

/**
 * The store's home layout as plain arrays (VEN-032, decision #117 b) for the Store API
 * `home` (mobile app) and custom Twig themes: enabled section keys in order (`layout`) and
 * one entry per section with its page-builder result — title / subtitle in the request
 * language (null = theme text), layout options, product cards when the store re-sourced the
 * section (null = the theme's own data in `data.sections`), picked banners, raw settings.
 */
class HomeSectionPayloads
{
    public function __construct(
        private readonly HomeLayoutService $layouts,
        private readonly HomeSectionHydrator $hydrator,
    ) {}

    /**
     * @return array{layout: list<string>, sections: list<array<string, mixed>>}
     */
    public function build(ResolvedThemeData $theme, Request $request): array
    {
        $sections = $this->hydrator->hydrate($theme->family, $this->layouts->sections($theme));

        return [
            'layout' => array_column($sections, 'key'),
            'sections' => array_map(static fn (array $section): array => [
                'key' => (string) $section['key'],
                'type' => (string) $section['type'],
                'data' => isset($section['data']) && is_string($section['data']) ? $section['data'] : null,
                'title' => $section['title_text'] ?? null,
                'subtitle' => $section['subtitle_text'] ?? null,
                'style' => isset($section['style']) && is_string($section['style']) ? $section['style'] : null,
                'rows' => isset($section['rows']) && is_string($section['rows']) ? $section['rows'] : null,
                'products' => isset($section['cards']) ? CatalogCards::present($section['cards'], $request) : null,
                'banners' => isset($section['banners']) ? array_map(static fn (BannerData $banner): array => $banner->toArray(), $section['banners']) : null,
                'settings' => $section['settings'] ?? [],
            ], $sections),
        ];
    }
}
