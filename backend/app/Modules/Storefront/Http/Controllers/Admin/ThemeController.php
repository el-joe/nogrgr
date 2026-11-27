<?php

namespace App\Modules\Storefront\Http\Controllers\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Storefront\Http\Requests\Admin\ThemeCountriesRequest;
use App\Modules\Storefront\Http\Requests\Admin\ThemeMetaRequest;
use App\Modules\Storefront\Http\Requests\Admin\ThemeSectionsRequest;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Services\Themes\HomeLayoutService;
use App\Modules\Storefront\Services\Themes\ThemeCatalogService;
use App\Modules\Storefront\Support\ThemeSectionLibrary;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Content › Storefront › Themes (ADM-086 / 086a, decisions #119 / #120; old
 * TemplateControlPage): the 13 catalogue themes as cards (preview, status, default
 * badge, countries) + the stores' approved custom themes; activate / deactivate and set
 * default with confirmation, edit modal (name, description per locale, preview image),
 * Countries modal (toggle each / all). Home sections per theme (ADM-087; old
 * HomeVariantsPage): toggle + drag order of the theme's section library, save / reset.
 */
class ThemeController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly ThemeCatalogService $catalog,
        private readonly HomeLayoutService $layouts,
        private readonly CountryService $countries,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(): View
    {
        return view('storefront::admin.themes.index', [
            'themes' => $this->catalog->catalogue(),
            'customThemes' => $this->catalog->customThemes(),
        ]);
    }

    public function activate(Theme $theme): JsonResponse
    {
        $this->catalog->setActive($theme, true);

        return JsonResponder::success(__('content::admin_themes.activated', ['theme' => $theme->name]), redirect: route('admin.storefront.themes.index'));
    }

    public function deactivate(Theme $theme): JsonResponse
    {
        $this->catalog->setActive($theme, false);

        return JsonResponder::success(__('content::admin_themes.deactivated', ['theme' => $theme->name]), redirect: route('admin.storefront.themes.index'));
    }

    public function makeDefault(Theme $theme): JsonResponse
    {
        $this->catalog->setDefault($theme);

        return JsonResponder::success(__('content::admin_themes.default_set', ['theme' => $theme->name]), redirect: route('admin.storefront.themes.index'));
    }

    public function edit(Request $request, Theme $theme): View
    {
        $title = __('content::admin_themes.edit_title', ['theme' => $theme->name]);
        $index = route('admin.storefront.themes.index');

        return $this->remoteModal($request, 'storefront::admin.themes._form', [
            'theme' => $theme,
            'title' => $title,
            'locales' => $this->locales->all(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            formId: 'theme-form',
            submitLabel: __('content::admin_themes.save'),
            breadcrumbs: $this->crumbs($title),
        ));
    }

    public function update(ThemeMetaRequest $request, Theme $theme): JsonResponse
    {
        $this->catalog->update($theme, $request->toData());

        return JsonResponder::success(__('content::admin_themes.updated', ['theme' => $theme->name]), redirect: route('admin.storefront.themes.index'));
    }

    public function countries(Request $request, Theme $theme): View
    {
        $title = __('content::admin_themes.countries_title', ['theme' => $theme->name]);
        $index = route('admin.storefront.themes.index');

        return $this->remoteModal($request, 'storefront::admin.themes._countries-form', [
            'theme' => $theme,
            'title' => $title,
            'countries' => $this->countries->options(),
            'selected' => $theme->countries()->pluck('countries.id')->map(static fn ($id): int => (int) $id)->all(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            formId: 'theme-countries-form',
            submitLabel: __('content::admin_themes.save'),
            breadcrumbs: $this->crumbs($title),
        ));
    }

    public function updateCountries(ThemeCountriesRequest $request, Theme $theme): JsonResponse
    {
        $this->catalog->syncCountries($theme, $request->countryIds());

        return JsonResponder::success(__('content::admin_themes.countries_saved', ['theme' => $theme->name]), redirect: route('admin.storefront.themes.index'));
    }

    /**
     * ADM-087: home sections of one theme (theme picker on the page).
     */
    public function sections(Request $request): View
    {
        $themes = $this->catalog->catalogue();
        $theme = $themes->firstWhere('id', (int) $request->query('theme')) ?? $themes->firstWhere('is_default', true) ?? $themes->first();

        return view('storefront::admin.themes.sections', [
            'themes' => $themes,
            'theme' => $theme,
            'sections' => $theme !== null ? array_map(static fn (array $section): array => $section + [
                'label' => ThemeSectionLibrary::label($section),
                'style_label' => ThemeSectionLibrary::styleLabel($section),
            ], $this->layouts->editable($theme)) : [],
        ]);
    }

    public function updateSections(ThemeSectionsRequest $request, Theme $theme): JsonResponse
    {
        $this->catalog->saveHomeSections($theme, $request->order(), $request->enabled());

        return JsonResponder::success(__('content::admin_themes.sections_saved', ['theme' => $theme->name]), redirect: route('admin.storefront.home-sections.index', ['theme' => $theme->id]));
    }

    public function resetSections(Theme $theme): JsonResponse
    {
        $this->catalog->resetHomeSections($theme);

        return JsonResponder::success(__('content::admin_themes.sections_reset', ['theme' => $theme->name]), redirect: route('admin.storefront.home-sections.index', ['theme' => $theme->id]));
    }

    /**
     * @return array<string, string|null>
     */
    private function crumbs(string $title): array
    {
        return [__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.themes') => route('admin.storefront.themes.index'), $title => null];
    }
}
