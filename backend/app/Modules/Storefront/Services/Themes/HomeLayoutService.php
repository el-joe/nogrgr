<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Services\Layouts\StoreLayoutPreviewService;
use App\Modules\Storefront\Services\Layouts\StoreLayoutService;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\ThemeSectionLibrary;
use App\Shared\Services\BaseService;

/**
 * Home section defaults per catalogue theme (ADM-087, decision #120): the theme's
 * `home_sections` ([{key, enabled}] in display order, null = library order, all on)
 * applied to its section library. Sections the config does not mention (new in code) are
 * appended switched on; keys the library no longer has are ignored. The page builder
 * (VEN-032) starts from this layout; on the storefront the store's published page-builder
 * layout (StoreLayoutService::homeItems) replaces it, sections hidden in the shopping
 * country are left out.
 */
class HomeLayoutService extends BaseService
{
    public function __construct(private readonly ThemeRepositoryContract $themes) {}

    /**
     * Every library section of the theme, in the configured order, with `enabled`.
     *
     * @return list<array<string, mixed>>
     */
    public function editable(Theme $theme): array
    {
        return $this->arrange($theme->family, (int) $theme->variant, $theme->home_sections);
    }

    /**
     * Enabled sections, in order, for rendering a theme's home.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(ResolvedThemeData $resolved): array
    {
        $theme = $resolved->id !== null ? $this->themes->find($resolved->id) : $this->themes->findBySlug($resolved->slug);
        $config = $theme instanceof Theme && $theme->family === $resolved->family && (int) $theme->variant === $resolved->variant ? $theme->home_sections : null;
        $countryId = null;
        $store = null;

        // The store's published page-builder layout (or the signed draft preview), VEN-032.
        if (tenancy()->initialized) {
            $countryId = app(StorefrontContext::class)->countryId();
            $store = app(StoreLayoutService::class)->homeItems($resolved, $countryId, app(StoreLayoutPreviewService::class)->current(request()));
        }

        return array_values(array_filter(
            $this->arrange($resolved->family, $resolved->variant, $store ?? $config),
            static fn (array $section): bool => $section['enabled'] && ($section['countries'] === [] || ($countryId !== null && in_array($countryId, $section['countries'], true))),
        ));
    }

    /**
     * Library sections of a family / variant arranged by page-builder items (carrying
     * `settings` and `countries`).
     *
     * @param  list<array<string, mixed>>|null  $items
     * @return list<array<string, mixed>>
     */
    public function arrangeItems(ThemeFamily $family, int $variant, ?array $items): array
    {
        return $this->arrange($family, $variant, $items);
    }

    /**
     * Enabled section keys in order (Store API home / mobile app).
     *
     * @return list<string>
     */
    public function keys(ResolvedThemeData $resolved): array
    {
        return array_column($this->sections($resolved), 'key');
    }

    /**
     * Normalise an admin submission: known keys only, each once, in the given order;
     * library sections left out are appended disabled.
     *
     * @param  list<string>  $order
     * @param  list<string>  $enabled
     * @return list<array{key: string, enabled: bool}>
     */
    public function normalise(Theme $theme, array $order, array $enabled): array
    {
        $library = ThemeSectionLibrary::keys($theme->family, (int) $theme->variant);
        $keys = array_values(array_unique(array_merge(array_values(array_intersect($order, $library)), $library)));

        return array_map(static fn (string $key): array => ['key' => $key, 'enabled' => in_array($key, $enabled, true)], $keys);
    }

    /**
     * @param  list<array<string, mixed>>|null  $config
     * @return list<array<string, mixed>>
     */
    private function arrange(ThemeFamily $family, int $variant, ?array $config): array
    {
        $library = [];

        foreach (ThemeSectionLibrary::sections($family, $variant) as $section) {
            // A library section may be off by default (`enabled` => false) until switched on.
            $library[$section['key']] = $section + ['enabled' => true, 'settings' => [], 'countries' => []];
        }

        $ordered = [];

        foreach ($config ?? [] as $item) {
            $key = is_array($item) ? ($item['key'] ?? null) : null;

            if (is_string($key) && isset($library[$key]) && ! isset($ordered[$key])) {
                $ordered[$key] = [
                    'enabled' => (bool) ($item['enabled'] ?? $library[$key]['enabled']),
                    'settings' => is_array($item['settings'] ?? null) ? $item['settings'] : [],
                    'countries' => is_array($item['countries'] ?? null) ? array_values(array_map('intval', $item['countries'])) : [],
                ] + $library[$key];
            }
        }

        foreach ($library as $key => $section) {
            $ordered[$key] ??= $section;
        }

        return array_values($ordered);
    }
}
