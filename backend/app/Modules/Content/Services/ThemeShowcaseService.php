<?php

namespace App\Modules\Content\Services;

use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Services\Themes\ThemePreviewService;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Central theme gallery + live preview (WEB-004, decisions #117 a / #119): the 13 active
 * catalogue themes, and for one theme the data of the preview page — the real theme
 * rendered by the platform demo store (Platform\Support\DemoStore) through a signed
 * theme-preview link (Storefront ThemePreviewService), the theme switcher list and the
 * registration link with the theme preselected. Cached per theme + locale for
 * CACHE_SECONDS (shorter than the signed link's lifetime).
 */
class ThemeShowcaseService extends BaseService
{
    public const CACHE_SECONDS = 300;

    public function __construct(
        private readonly ThemeRepositoryContract $themes,
        private readonly TenantRepositoryContract $tenants,
        private readonly ThemePreviewService $previews,
    ) {}

    /**
     * Active catalogue themes in display order.
     *
     * @return Collection<int, Theme>
     */
    public function gallery(): Collection
    {
        return $this->themes->gallery();
    }

    /**
     * Preview page data of an active theme, or null when the theme is unknown / inactive.
     *
     * @return array{slug: string, name: string, description: ?string, family: string, image: ?string, frameUrl: ?string, registerUrl: string, themes: list<array{slug: string, name: string, url: string}>}|null
     */
    public function preview(string $slug, string $locale, Request $request): ?array
    {
        $key = 'central:theme-preview:'.$slug.':'.$locale.':'.$request->getHttpHost();

        $data = Cache::remember($key, self::CACHE_SECONDS, function () use ($slug, $locale, $request): array {
            $theme = $this->themes->findActiveBySlug($slug);

            if ($theme === null) {
                return [];
            }

            return [
                'slug' => $theme->slug,
                'name' => (string) $theme->name,
                'description' => filled($theme->description) ? (string) $theme->description : null,
                'family' => $theme->family->value,
                'image' => $theme->preview_url,
                'frameUrl' => $this->frameUrl($theme, $locale, $request),
                'registerUrl' => $this->registerUrl($theme),
                'themes' => $this->themes->active()->map(static fn (Theme $item): array => [
                    'slug' => $item->slug,
                    'name' => (string) $item->name,
                    'url' => route('central.themes.show', ['slug' => $item->slug]),
                ])->values()->all(),
            ];
        });

        return $data === [] ? null : $data;
    }

    public function registerUrl(Theme $theme): string
    {
        return Route::has('central.register') ? route('central.register', ['theme' => $theme->slug]) : url('/');
    }

    /**
     * Signed demo-store URL rendering the theme (null when no platform demo store exists).
     */
    private function frameUrl(Theme $theme, string $locale, Request $request): ?string
    {
        $store = $this->tenants->platformDemoStore();

        if ($store === null) {
            return null;
        }

        $url = $this->previews->url($store, $theme->slug).'&lang='.$locale;
        $port = $request->getPort();

        // Keep a development port (php artisan serve) on the store sub-domain.
        if (! in_array($port, [80, 443, null], true)) {
            $url = preg_replace('#^(https?://[^/:]+)(?=/)#', '$1:'.$port, $url, 1) ?? $url;
        }

        return $url;
    }
}
