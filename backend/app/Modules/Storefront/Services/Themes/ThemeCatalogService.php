<?php

namespace App\Modules\Storefront\Services\Themes;

use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\DTOs\Themes\ThemeMetaData;
use App\Modules\Storefront\Models\CustomTheme;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Repositories\Contracts\ThemeRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin › Storefront › Themes (ADM-086 / 086a / 087, decisions #119 / #120): the 13
 * catalogue themes — activate / deactivate, platform default (exactly one, always
 * active), name / description / preview image, allowed countries, home section defaults
 * — plus the stores' approved custom Twig themes (read-only; owned by the custom-theme
 * module). Stores keep their saved choice; StoreThemeService falls back at render time.
 */
class ThemeCatalogService extends BaseService
{
    public function __construct(
        private readonly ThemeRepositoryContract $themes,
        private readonly ThemeImageStore $images,
        private readonly HomeLayoutService $layouts,
    ) {}

    /**
     * @return Collection<int, Theme>
     */
    public function catalogue(): Collection
    {
        return $this->themes->catalogue();
    }

    /**
     * Stores' custom Twig themes with a published (approved) version.
     *
     * @return \Illuminate\Support\Collection<int, CustomTheme>
     */
    public function customThemes(): \Illuminate\Support\Collection
    {
        if (! DB::connection((new CustomTheme)->getConnectionName())->getSchemaBuilder()->hasTable('custom_themes')) {
            return collect();
        }

        return CustomTheme::withoutTenantScope()
            ->whereNotNull('live_version_id')
            ->with(['tenant', 'liveVersion:id,version,published_at,reviewed_at'])
            ->orderBy('name')
            ->get();
    }

    public function setActive(Theme $theme, bool $active): Theme
    {
        if (! $active && $theme->is_default) {
            throw ValidationException::withMessages(['is_active' => __('content::admin_themes.errors.default_inactive')]);
        }

        $theme->forceFill(['is_active' => $active])->save();

        return $theme;
    }

    public function setDefault(Theme $theme): Theme
    {
        if (! $theme->is_active) {
            throw ValidationException::withMessages(['is_default' => __('content::admin_themes.errors.default_must_be_active')]);
        }

        DB::connection($theme->getConnectionName())->transaction(function () use ($theme): void {
            Theme::query()->whereKeyNot($theme->id)->where('is_default', true)->update(['is_default' => false]);
            $theme->forceFill(['is_default' => true])->save();
        });

        return $theme;
    }

    public function update(Theme $theme, ThemeMetaData $data): Theme
    {
        $old = $theme->preview_path;
        $path = $old;

        if ($data->preview !== null) {
            $path = $this->images->store($data->preview);
        } elseif ($data->removePreview) {
            $path = null;
        }

        $theme->replaceTranslations('name', array_filter($data->name, static fn (?string $text): bool => $text !== null && $text !== ''));
        $theme->replaceTranslations('description', array_filter($data->description, static fn (?string $text): bool => $text !== null && $text !== ''));
        $theme->preview_path = $path;
        $theme->save();

        if ($old !== $path) {
            $this->images->delete($old);
        }

        return $theme;
    }

    /**
     * Allowed countries (empty = every country).
     *
     * @param  list<int>  $countryIds
     */
    public function syncCountries(Theme $theme, array $countryIds): Theme
    {
        $ids = Country::query()->whereKey($countryIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $theme->countries()->sync($ids);
        $theme->unsetRelation('countries');

        return $theme;
    }

    /**
     * Home section defaults (ADM-087): order + on / off of the theme's section library.
     *
     * @param  list<string>  $order
     * @param  list<string>  $enabled
     */
    public function saveHomeSections(Theme $theme, array $order, array $enabled): Theme
    {
        if ($enabled === []) {
            throw ValidationException::withMessages(['enabled' => __('content::admin_themes.errors.no_sections')]);
        }

        $theme->home_sections = $this->layouts->normalise($theme, $order, $enabled);
        $theme->save();

        return $theme;
    }

    /**
     * Back to the theme's library order with every section on.
     */
    public function resetHomeSections(Theme $theme): Theme
    {
        $theme->home_sections = null;
        $theme->save();

        return $theme;
    }
}
