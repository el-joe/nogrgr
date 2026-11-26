<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * The store's active theme per country (tenant `store_settings` key `store_theme`,
 * VEN-030 / 030a / 030b service API; the vendor page comes later):
 *
 *   {"default": "ecommet", "countries": {"191": "elora-3"}}
 *
 * A theme slug carries its family and home variant (central `themes` catalogue). A
 * country without its own entry uses the default; a missing / deactivated theme falls
 * back to the tenant's registration family (`theme_family`) and then to Ecommet.
 */
class StoreThemeService extends BaseService
{
    public const DEFAULT_SLUG = 'ecommet';

    /**
     * @var array<string, ResolvedThemeData>
     */
    private array $memo = [];

    public function __construct(
        private readonly StoreSettingRepositoryContract $settings,
        private readonly ThemeRepositoryContract $themes,
        private readonly StoreCountries $countries,
    ) {}

    public function resolve(?int $countryId): ResolvedThemeData
    {
        $config = $this->config();
        $slug = ($countryId !== null ? ($config['countries'][$countryId] ?? null) : null) ?? $config['default'];
        $key = (tenancy()->initialized ? (string) tenant()?->getTenantKey() : 'central').'|'.($slug ?? '');

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $theme = $slug !== null ? $this->themes->findActiveBySlug($slug) : null;
        $theme ??= $this->registrationTheme();

        if ($theme !== null) {
            return $this->memo[$key] = new ResolvedThemeData($theme->slug, $theme->family, (int) $theme->variant);
        }

        // Catalogue not seeded / theme off: keep the registration family (variant 1).
        $family = tenancy()->initialized ? ThemeFamily::tryFrom((string) tenant('theme_family')) : null;

        return $this->memo[$key] = new ResolvedThemeData($family->value ?? self::DEFAULT_SLUG, $family ?? ThemeFamily::Ecommet, 1);
    }

    /**
     * @return array{default: string|null, countries: array<int, string>}
     */
    public function config(): array
    {
        $value = $this->settings->get(StoreSettingKey::StoreTheme);
        $value = is_array($value) ? $value : [];
        $countries = is_array($value['countries'] ?? null) ? $value['countries'] : [];

        $map = [];

        foreach ($countries as $countryId => $slug) {
            if (is_numeric($countryId) && is_string($slug) && $slug !== '') {
                $map[(int) $countryId] = $slug;
            }
        }

        return [
            'default' => is_string($value['default'] ?? null) ? $value['default'] : null,
            'countries' => $map,
        ];
    }

    /**
     * Activate a theme for the whole store (no countries) or for the given countries (VEN-030a).
     *
     * @param  list<int>  $countryIds
     */
    public function activate(string $slug, array $countryIds = []): void
    {
        $theme = $this->activeTheme($slug);
        $config = $this->config();

        if ($countryIds === []) {
            $config['default'] = $theme->slug;
        } else {
            foreach ($this->sellingCountries($countryIds) as $countryId) {
                $config['countries'][$countryId] = $theme->slug;
            }
        }

        $this->save($config);
    }

    /**
     * Pick the home variant of a family for one country (VEN-030b): the theme of that
     * family + variant in the catalogue.
     */
    public function setCountryVariant(int $countryId, ThemeFamily $family, int $variant): void
    {
        $theme = $this->themes->active()->first(static fn (Theme $theme): bool => $theme->family === $family && (int) $theme->variant === $variant)
            ?? throw ValidationException::withMessages(['variant' => __('validation.exists', ['attribute' => 'variant'])]);

        $this->activate($theme->slug, [$countryId]);
    }

    /**
     * Drop a country's own theme (it follows the store default again).
     */
    public function resetCountry(int $countryId): void
    {
        $config = $this->config();
        unset($config['countries'][$countryId]);
        $this->save($config);
    }

    /**
     * @param  array{default: string|null, countries: array<int, string>}  $config
     */
    private function save(array $config): void
    {
        $this->settings->put(StoreSettingKey::StoreTheme, $config);
        $this->memo = [];
    }

    private function activeTheme(string $slug): Theme
    {
        return $this->themes->findActiveBySlug($slug)
            ?? throw ValidationException::withMessages(['theme' => __('validation.exists', ['attribute' => 'theme'])]);
    }

    /**
     * @param  list<int>  $countryIds
     * @return list<int>
     */
    private function sellingCountries(array $countryIds): array
    {
        $ids = [];

        foreach ($countryIds as $countryId) {
            if (! $this->countries->isAvailable((int) $countryId)) {
                throw ValidationException::withMessages(['countries' => __('validation.exists', ['attribute' => 'country'])]);
            }

            $ids[] = (int) $countryId;
        }

        return $ids;
    }

    private function registrationTheme(): ?Theme
    {
        $family = tenancy()->initialized ? ThemeFamily::tryFrom((string) tenant('theme_family')) : null;

        if ($family === null) {
            return $this->themes->findActiveBySlug(self::DEFAULT_SLUG);
        }

        return $this->themes->active()->first(static fn (Theme $theme): bool => $theme->family === $family);
    }
}
