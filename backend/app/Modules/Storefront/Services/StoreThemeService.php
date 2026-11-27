<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Storefront\DTOs\ResolvedThemeData;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The store's theme per country (tenant `store_settings` key `store_theme`, decisions #76 /
 * #119 / #120), stored as central catalogue theme ids:
 *
 *   {"default": 1, "countries": {"191": 4}}
 *
 * Resolution for a shopping country: the country's own theme → the store default → (no
 * setting saved yet) the registration family's first theme → the platform default theme
 * → the first active theme. A candidate counts only while it is active and allowed in that
 * country (theme_country, empty = everywhere), so a deactivated or country-restricted
 * theme falls back without touching the store's saved choice. Leftover slug values (before
 * the id migration) are still read.
 */
class StoreThemeService extends BaseService
{
    public const DEFAULT_SLUG = 'ecommet';

    /**
     * @var Collection<int, Theme>|null
     */
    private ?Collection $active = null;

    /**
     * @var array<string, ResolvedThemeData>
     */
    private array $memo = [];

    public function __construct(
        private readonly StoreSettingRepositoryContract $settings,
        private readonly ThemeRepositoryContract $themes,
        private readonly StoreCountries $countries,
        private readonly CustomThemeRepositoryContract $customThemes,
    ) {}

    /**
     * The theme a country renders: the built-in catalogue theme, with the store's live
     * custom Twig theme version on top when it has one (decision #121).
     */
    public function resolve(?int $countryId): ResolvedThemeData
    {
        $builtIn = $this->resolveBuiltIn($countryId);

        if (! tenancy()->initialized) {
            return $builtIn;
        }

        $live = $this->customThemes->liveVersionForTenant((int) tenant()?->getTenantKey());

        return $live !== null ? $builtIn->withCustom((int) $live->id, (string) $live->name) : $builtIn;
    }

    /**
     * The built-in catalogue theme a country renders (no custom theme overlay).
     */
    public function resolveBuiltIn(?int $countryId): ResolvedThemeData
    {
        $key = (tenancy()->initialized ? (string) tenant()?->getTenantKey() : 'central').'|'.($countryId ?? '');

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $theme = $this->resolveTheme($countryId);

        if ($theme !== null) {
            return $this->memo[$key] = ResolvedThemeData::fromTheme($theme);
        }

        // Catalogue not seeded / every theme off: keep the registration family (variant 1).
        $family = tenancy()->initialized ? ThemeFamily::tryFrom((string) tenant('theme_family')) : null;

        return $this->memo[$key] = new ResolvedThemeData($family->value ?? self::DEFAULT_SLUG, $family ?? ThemeFamily::Ecommet, 1);
    }

    /**
     * The catalogue theme a country renders (null when the catalogue has no usable theme).
     */
    public function resolveTheme(?int $countryId): ?Theme
    {
        $config = $this->config();
        $candidates = [
            $countryId !== null ? ($config['countries'][$countryId] ?? null) : null,
            $config['default'],
        ];

        foreach ($candidates as $id) {
            $theme = $id !== null ? $this->activeThemes()->find($id) : null;

            if ($theme instanceof Theme && $theme->allowsCountry($countryId)) {
                return $theme;
            }
        }

        if (! $config['saved']) {
            $registration = $this->registrationTheme($countryId);

            if ($registration !== null) {
                return $registration;
            }
        }

        $platform = $this->activeThemes()->firstWhere('is_default', true);

        if ($platform instanceof Theme && $platform->allowsCountry($countryId)) {
            return $platform;
        }

        return $this->activeThemes()->first(static fn (Theme $theme): bool => $theme->allowsCountry($countryId));
    }

    /**
     * The saved setting (ids). saved = the store has saved a theme choice (or a reset) at least once.
     *
     * @return array{default: int|null, countries: array<int, int>, saved: bool}
     */
    public function config(): array
    {
        $raw = $this->settings->get(StoreSettingKey::StoreTheme);
        $value = is_array($raw) ? $raw : [];
        $countries = is_array($value['countries'] ?? null) ? $value['countries'] : [];

        $map = [];

        foreach ($countries as $countryId => $theme) {
            $id = $this->toId($theme);

            if (is_numeric($countryId) && $id !== null) {
                $map[(int) $countryId] = $id;
            }
        }

        return [
            'default' => $this->toId($value['default'] ?? null),
            'countries' => $map,
            'saved' => is_array($raw),
        ];
    }

    /**
     * Active catalogue themes (allowed countries loaded), display order.
     *
     * @return Collection<int, Theme>
     */
    public function activeThemes(): Collection
    {
        return $this->active ??= $this->themes->activeWithCountries();
    }

    /**
     * Active themes allowed in at least one of the given countries (or everywhere).
     *
     * @param  list<int>  $countryIds
     * @return Collection<int, Theme>
     */
    public function availableIn(array $countryIds): Collection
    {
        return $this->activeThemes()
            ->filter(static fn (Theme $theme): bool => $theme->countries->isEmpty() || $countryIds === [] || array_intersect($theme->countries->modelKeys(), $countryIds) !== [])
            ->values();
    }

    /**
     * Activate a theme (id or slug) for the whole store (no countries) or for the given
     * selling countries (VEN-030a). Each country must be one the theme is allowed in.
     *
     * @param  list<int>  $countryIds
     */
    public function activate(int|string $theme, array $countryIds = []): Theme
    {
        $model = $this->activeTheme($theme);
        $config = $this->config();

        if ($countryIds === []) {
            $config['default'] = (int) $model->id;
        } else {
            foreach ($this->sellingCountries($countryIds) as $countryId) {
                if (! $model->allowsCountry($countryId)) {
                    throw ValidationException::withMessages(['countries' => __('content::vendor_themes.errors.country_not_allowed', ['theme' => $model->name])]);
                }

                $config['countries'][$countryId] = (int) $model->id;
            }
        }

        $this->save($config);

        return $model;
    }

    /**
     * Pick the theme of a family + variant for one country (kept for the setup / API callers).
     */
    public function setCountryVariant(int $countryId, ThemeFamily $family, int $variant): void
    {
        $theme = $this->activeThemes()->first(static fn (Theme $theme): bool => $theme->family === $family && (int) $theme->variant === $variant)
            ?? throw ValidationException::withMessages(['variant' => __('validation.exists', ['attribute' => 'variant'])]);

        $this->activate((int) $theme->id, [$countryId]);
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
     * Forget every choice: the whole store uses the platform default theme.
     */
    public function resetToPlatformDefault(): void
    {
        $this->save(['default' => null, 'countries' => [], 'saved' => true]);
    }

    /**
     * Drop the per-request memo (theme saved, custom theme published / switched).
     */
    public function forget(): void
    {
        $this->memo = [];
        $this->customThemes->forget();
    }

    /**
     * @param  array{default: int|null, countries: array<int, int>, saved?: bool}  $config
     */
    private function save(array $config): void
    {
        $countries = [];

        foreach ($config['countries'] as $countryId => $id) {
            $countries[(string) $countryId] = $id;
        }

        $this->settings->put(StoreSettingKey::StoreTheme, ['default' => $config['default'], 'countries' => (object) $countries]);
        $this->memo = [];
    }

    private function toId(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        if (is_string($value) && $value !== '') {
            $theme = $this->activeThemes()->firstWhere('slug', $value) ?? $this->themes->findBySlug($value);

            return $theme !== null ? (int) $theme->id : null;
        }

        return null;
    }

    private function activeTheme(int|string $theme): Theme
    {
        $model = is_int($theme) || ctype_digit($theme)
            ? $this->activeThemes()->find((int) $theme)
            : $this->activeThemes()->firstWhere('slug', $theme);

        return $model instanceof Theme ? $model
            : throw ValidationException::withMessages(['theme' => __('validation.exists', ['attribute' => 'theme'])]);
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

    private function registrationTheme(?int $countryId): ?Theme
    {
        $family = tenancy()->initialized ? ThemeFamily::tryFrom((string) tenant('theme_family')) : null;

        if ($family === null) {
            return null;
        }

        return $this->activeThemes()->first(static fn (Theme $theme): bool => $theme->family === $family && $theme->allowsCountry($countryId));
    }
}
