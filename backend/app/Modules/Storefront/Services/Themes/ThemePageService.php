<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Storefront\DTOs\ThemePageActivationData;
use App\Modules\Storefront\DTOs\ThemePageRowData;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Page model of Vendor › Your store › Themes (VEN-030 / 030a / 030b) and Home variants
 * (VEN-031). Reads the central `themes` catalogue and the store's `store_theme` setting
 * and writes only through StoreThemeService (decision #76): one scope per tab / row —
 * the store default ("default") or one of the store's selling countries.
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
        private readonly ThemeRepositoryContract $repository,
        private readonly StoreCountries $countries,
        private readonly ThemePreviewService $previews,
    ) {}

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
     * Active catalogue themes in display order.
     *
     * @return Collection<int, Theme>
     */
    public function catalogue(): Collection
    {
        return $this->catalogue ??= $this->repository->active();
    }

    public function findTheme(string $slug): ?Theme
    {
        return $this->catalogue()->firstWhere('slug', $slug);
    }

    /**
     * Families with at least one active theme: family value => its themes (variant order).
     *
     * @return array<string, Collection<int, Theme>>
     */
    public function families(): array
    {
        $families = [];

        foreach (ThemeFamily::cases() as $family) {
            $themes = $this->catalogue()->filter(static fn (Theme $theme): bool => $theme->family === $family)->sortBy('variant')->values();

            if ($themes->isNotEmpty()) {
                $families[$family->value] = $themes;
            }
        }

        return $families;
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
        $resolved = $this->themes->resolve($countryId);
        $config = $this->themes->config();

        return new ThemePageRowData(
            countryId: $countryId,
            label: $countryId === null ? (string) __('content::vendor_themes.store_default') : $this->countries()[$countryId],
            theme: $this->findTheme($resolved->slug),
            ownTheme: $countryId !== null && isset($config['countries'][$countryId]),
        );
    }

    /**
     * Selling countries that have this exact theme as their own theme.
     *
     * @return list<int>
     */
    public function countriesUsing(string $slug): array
    {
        $own = array_filter($this->themes->config()['countries'], static fn (string $value): bool => $value === $slug);

        return array_values(array_intersect(array_keys($own), array_keys($this->countries())));
    }

    /**
     * Activate a theme for the store default and / or some selling countries (VEN-030a).
     */
    public function activate(ThemePageActivationData $data): Theme
    {
        $theme = $this->findTheme($data->slug)
            ?? throw ValidationException::withMessages(['theme' => __('content::vendor_themes.errors.theme_unavailable')]);

        DB::transaction(function () use ($data, $theme): void {
            if ($data->asDefault) {
                $this->themes->activate($theme->slug);
            }

            if ($data->countryIds !== []) {
                $this->themes->activate($theme->slug, $data->countryIds);
            }
        });

        return $theme;
    }

    /**
     * Pick the home variant of the scope's current family (VEN-030b / VEN-031).
     */
    public function setVariant(string $scope, int $variant): Theme
    {
        $countryId = $this->countryOf($scope);
        $family = $this->themes->resolve($countryId)->family;
        $theme = $this->catalogue()->first(static fn (Theme $theme): bool => $theme->family === $family && (int) $theme->variant === $variant)
            ?? throw ValidationException::withMessages(['variant' => __('content::vendor_themes.errors.variant_unavailable')]);

        if ($countryId === null) {
            $this->themes->activate($theme->slug);
        } else {
            $this->themes->setCountryVariant($countryId, $family, $variant);
        }

        return $theme;
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
     * Variants of the scope's current family for the radio list: variant => label.
     *
     * @return array<int, string>
     */
    public function variantOptions(string $scope): array
    {
        $family = $this->themes->resolve($this->countryOf($scope))->family;

        return $this->catalogue()
            ->filter(static fn (Theme $theme): bool => $theme->family === $family)
            ->sortBy('variant')
            ->mapWithKeys(static fn (Theme $theme): array => [(int) $theme->variant => (string) __('content::vendor_themes.home_variant', ['number' => $theme->variant])])
            ->all();
    }

    public function previewUrl(Theme $theme, ?int $countryId): string
    {
        return $this->previews->url(StoreContext::tenant(), $theme->slug, $countryId);
    }
}
