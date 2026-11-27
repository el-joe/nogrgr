<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Storefront\DTOs\ThemePageActivationData;
use App\Modules\Storefront\DTOs\ThemePageRowData;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Page model of Vendor › Your store › Themes (VEN-030 / 030a / 030b) and Themes by
 * country (VEN-031), decision #119: the catalogue is a flat list of independent themes
 * (family / variant stay internal). Lists the active themes allowed in at least one of
 * the store's selling countries, reads the `store_theme` setting and writes only through
 * StoreThemeService (decision #76): one scope per row — the store default ("default")
 * or one of the store's selling countries.
 */
class ThemePageService extends BaseService
{
    public const DEFAULT_SCOPE = 'default';

    public const SCOPE_PATTERN = 'default|[0-9]+';

    /**
     * @var Collection<int, Theme>|null
     */
    private ?Collection $catalogue = null;

    public function __construct(
        private readonly StoreThemeService $themes,
        private readonly StoreCountries $countries,
        private readonly ThemePreviewService $previews,
        private readonly CustomThemeRepositoryContract $customThemes,
    ) {}

    /**
     * The store's custom Twig theme as an extra entry of the theme list (#119 / #121 seam):
     * only once a version was published (approved + live). `active` = the custom theme
     * switch: on, it renders the pages it supports in every country on top of the
     * built-in theme chosen below (which keeps checkout / account / fallback pages).
     *
     * @return array{name: string, version: string, version_id: int, active: bool, published_at: string|null, approved_count: int}|null
     */
    public function customTheme(): ?array
    {
        $tenantId = (int) tenant()?->getTenantKey();
        $theme = $tenantId > 0 ? $this->customThemes->forTenant($tenantId) : null;
        $live = $theme?->live_version_id !== null ? $this->customThemes->findVersion((int) $theme->live_version_id) : null;

        if ($theme === null || $live === null || $live->status !== CustomThemeVersionStatus::Approved || (int) $live->tenant_id !== $tenantId) {
            return null;
        }

        return [
            'name' => (string) ($live->name ?: $theme->name),
            'version' => (string) $live->version,
            'version_id' => (int) $live->id,
            'active' => (bool) $theme->is_active,
            'published_at' => ($live->published_at ?? $live->reviewed_at)?->locale(app()->getLocale())->translatedFormat('j M Y'),
            'approved_count' => $this->customThemes->approvedForTenant($tenantId)->count(),
        ];
    }

    /**
     * The store's selling countries (id => name, sorted by name).
     *
     * @return array<int, string>
     */
    public function countries(): array
    {
        return $this->countries->available()->mapWithKeys(fn ($country): array => [(int) $country->id => (string) $country->name])->all();
    }

    /**
     * The requested scope when valid, else the store default.
     */
    public function scopeOrDefault(mixed $value): string
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) && array_key_exists($id, $this->countries()) ? (string) $id : self::DEFAULT_SCOPE;
    }

    /**
     * Country id of a scope (null = store default); 404 for a country the store does not sell in.
     */
    public function countryOf(string $scope): ?int
    {
        if ($scope === self::DEFAULT_SCOPE) {
            return null;
        }

        abort_unless(array_key_exists((int) $scope, $this->countries()), 404);

        return (int) $scope;
    }

    /**
     * Active themes the store may use: allowed in at least one selling country (or everywhere).
     *
     * @return Collection<int, Theme>
     */
    public function catalogue(): Collection
    {
        return $this->catalogue ??= $this->themes->availableIn(array_keys($this->countries()));
    }

    public function findTheme(int $id): ?Theme
    {
        return $this->catalogue()->find($id);
    }

    /**
     * The theme every store uses until it picks one (admin "default" theme).
     */
    public function platformDefault(): ?Theme
    {
        return $this->themes->activeThemes()->firstWhere('is_default', true);
    }

    /**
     * Store default first, then every selling country.
     *
     * @return list<ThemePageRowData>
     */
    public function rows(): array
    {
        $rows = [$this->row(self::DEFAULT_SCOPE)];

        foreach (array_keys($this->countries()) as $countryId) {
            $rows[] = $this->row((string) $countryId);
        }

        return $rows;
    }

    public function row(string $scope): ThemePageRowData
    {
        $countryId = $this->countryOf($scope);
        $config = $this->themes->config();

        return new ThemePageRowData(
            countryId: $countryId,
            label: $countryId === null ? (string) __('content::vendor_themes.store_default') : $this->countries()[$countryId],
            theme: $this->themes->resolveTheme($countryId),
            ownTheme: $countryId === null ? $config['default'] !== null : isset($config['countries'][$countryId]),
        );
    }

    /**
     * Where each theme is live: theme id => ['default' => bool, 'countries' => [names]].
     *
     * @return array<int, array{default: bool, countries: list<string>}>
     */
    public function usage(): array
    {
        $usage = [];

        foreach ($this->rows() as $row) {
            if ($row->theme === null) {
                continue;
            }

            $id = (int) $row->theme->id;
            $usage[$id] ??= ['default' => false, 'countries' => []];

            if ($row->countryId === null) {
                $usage[$id]['default'] = true;
            } else {
                $usage[$id]['countries'][] = $row->label;
            }
        }

        return $usage;
    }

    /**
     * Selling countries the theme may be used in (id => name).
     *
     * @return array<int, string>
     */
    public function allowedCountries(Theme $theme): array
    {
        return array_filter($this->countries(), static fn (string $name, int $id): bool => $theme->allowsCountry($id), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Selling countries that have this exact theme as their own theme.
     *
     * @return list<int>
     */
    public function countriesUsing(int $themeId): array
    {
        $own = array_keys(array_filter($this->themes->config()['countries'], static fn (int $id): bool => $id === $themeId));

        return array_values(array_intersect($own, array_keys($this->countries())));
    }

    /**
     * Activate a theme for the store default and / or some selling countries (VEN-030a).
     */
    public function activate(ThemePageActivationData $data): Theme
    {
        $theme = $this->findTheme($data->themeId)
            ?? throw ValidationException::withMessages(['theme' => __('content::vendor_themes.errors.theme_unavailable')]);

        foreach ($data->countryIds as $countryId) {
            if (! $theme->allowsCountry($countryId)) {
                throw ValidationException::withMessages(['country_ids' => __('content::vendor_themes.errors.country_not_allowed', ['theme' => $theme->name])]);
            }
        }

        DB::transaction(function () use ($data, $theme): void {
            if ($data->asDefault) {
                $this->themes->activate((int) $theme->id);
            }

            if ($data->countryIds !== []) {
                $this->themes->activate((int) $theme->id, $data->countryIds);
            }
        });

        return $theme;
    }

    /**
     * Themes offered for one scope (VEN-030b): every listed theme for the store default,
     * the ones allowed in that country otherwise (id => name).
     *
     * @return array<int, string>
     */
    public function optionsFor(string $scope): array
    {
        $countryId = $this->countryOf($scope);

        return $this->catalogue()
            ->filter(static fn (Theme $theme): bool => $theme->allowsCountry($countryId))
            ->mapWithKeys(static fn (Theme $theme): array => [(int) $theme->id => (string) $theme->name])
            ->all();
    }

    /**
     * Pick the theme of one scope (VEN-030b, Themes by country).
     */
    public function setScopeTheme(string $scope, int $themeId): Theme
    {
        $countryId = $this->countryOf($scope);

        return $this->activate(new ThemePageActivationData(
            themeId: $themeId,
            asDefault: $countryId === null,
            countryIds: $countryId === null ? [] : [$countryId],
        ));
    }

    /**
     * The country follows the store default again.
     */
    public function resetCountry(string $scope): void
    {
        $countryId = $this->countryOf($scope);

        if ($countryId === null) {
            throw ValidationException::withMessages(['country' => __('content::vendor_themes.errors.reset_default')]);
        }

        $this->themes->resetCountry($countryId);
    }

    /**
     * Forget every choice: the store follows the platform default theme everywhere.
     */
    public function resetToPlatformDefault(): void
    {
        $this->themes->resetToPlatformDefault();
    }

    public function previewUrl(Theme $theme, ?int $countryId): string
    {
        return $this->previews->url(StoreContext::tenant(), $theme->slug, $countryId);
    }
}
