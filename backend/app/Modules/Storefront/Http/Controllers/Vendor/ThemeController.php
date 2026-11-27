<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Http\Requests\Vendor\ThemeActivationRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ThemeScopeRequest;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Services\Themes\ThemePageService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Your store › Themes (VEN-030, Figma Vendor Panel 85:104756) and Themes by
 * country (VEN-031), decision #119: one card per theme (13 themes + custom ones) allowed
 * in the store's selling countries — Activate as store default, "Use in countries"
 * modal (VEN-030a, 85:110108), signed storefront preview; per-scope theme modal
 * (VEN-030b), reset a country, reset to the platform default. Writes go through
 * StoreThemeService only (decision #76); runs in the store's tenant context.
 */
class ThemeController extends Controller
{
    use RendersRemoteModals;

    /**
     * The page service per request (scoped): it memoises the catalogue and the store
     * setting, and the router keeps controller instances between requests.
     */
    private function pages(): ThemePageService
    {
        return app(ThemePageService::class);
    }

    public function index(Request $request): View
    {
        $themes = $this->pages()->catalogue();
        $countries = $this->pages()->countries();
        $allowed = [];

        foreach ($themes as $theme) {
            $allowed[(int) $theme->id] = $this->pages()->allowedCountries($theme);
        }

        return view('storefront::vendor.themes.index', [
            'themes' => $themes,
            'allowed' => $allowed,
            'rows' => $this->pages()->rows(),
            'usage' => $this->pages()->usage(),
            'countries' => $countries,
            'platformDefault' => $this->pages()->platformDefault(),
            'canManage' => $this->canManage($request),
            'customTheme' => $this->can($request, 'store.custom-themes.view') ? $this->pages()->customTheme() : null,
            'canManageCustom' => $this->can($request, 'store.custom-themes.manage'),
            'countriesUrl' => $this->can($request, 'onboarding.manage') ? vendor_route('onboarding.setup', ['step' => 'countries']) : null,
        ]);
    }

    /**
     * VEN-031 Themes by country (route kept as store.home-variants).
     */
    public function byCountry(Request $request): View
    {
        return view('storefront::vendor.themes.by-country', [
            'rows' => $this->pages()->rows(),
            'hasCatalogue' => $this->pages()->catalogue()->isNotEmpty(),
            'countries' => $this->pages()->countries(),
            'platformDefault' => $this->pages()->platformDefault(),
            'canManage' => $this->canManage($request),
        ]);
    }

    /**
     * VEN-030a: "Use <theme> in…" (selling countries the theme is allowed in + store default).
     */
    public function countries(Request $request, int $theme): View
    {
        $model = $this->theme($theme);
        $title = __('content::vendor_themes.use_in', ['theme' => $model->name]);
        $index = vendor_route('store.themes.index');

        return $this->remoteModal($request, 'storefront::vendor.themes._countries-form', [
            'theme' => $model,
            'title' => $title,
            'countries' => $this->pages()->allowedCountries($model),
            'restricted' => count($this->pages()->allowedCountries($model)) < count($this->pages()->countries()),
            'selected' => $this->pages()->countriesUsing((int) $model->id),
            'isDefault' => $this->pages()->row(ThemePageService::DEFAULT_SCOPE)->theme?->is($model) ?? false,
            'return' => $this->returnTarget($request),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-themes',
            formId: 'vendor-theme-countries-form',
            submitLabel: __('content::vendor_themes.save'),
            breadcrumbs: [__('navigation.vendor.store_design') => null, __('navigation.vendor.themes') => $index, $title => null],
        ));
    }

    public function activate(ThemeActivationRequest $request): JsonResponse
    {
        $theme = $this->pages()->activate($request->toData());

        return JsonResponder::success(
            __('content::vendor_themes.activated', ['theme' => $theme->name]),
            redirect: $this->redirectUrl($request),
        );
    }

    /**
     * VEN-030b: the theme of one scope (store default or a selling country).
     */
    public function scope(Request $request, string $scope): View
    {
        $row = $this->pages()->row($scope);
        $title = __('content::vendor_themes.scope_title', ['scope' => $row->label]);
        $back = vendor_route('store.home-variants');

        return $this->remoteModal($request, 'storefront::vendor.themes._scope-form', [
            'row' => $row,
            'title' => $title,
            'options' => $this->pages()->optionsFor($scope),
            'return' => $this->returnTarget($request),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $back,
            pageModule: 'vendor-themes',
            formId: 'vendor-theme-scope-form',
            submitLabel: __('content::vendor_themes.save'),
            breadcrumbs: [__('navigation.vendor.store_design') => null, __('content::vendor_themes.by_country_title') => $back, $title => null],
        ));
    }

    public function updateScope(ThemeScopeRequest $request, string $scope): JsonResponse
    {
        $label = $this->pages()->row($scope)->label;
        $theme = $this->pages()->setScopeTheme($scope, $request->themeId());

        return JsonResponder::success(
            __('content::vendor_themes.scope_saved', ['theme' => $theme->name, 'scope' => $label]),
            redirect: $this->redirectUrl($request),
        );
    }

    public function reset(Request $request, string $scope): JsonResponse
    {
        $label = $this->pages()->row($scope)->label;
        $this->pages()->resetCountry($scope);

        return JsonResponder::success(__('content::vendor_themes.reset_done', ['country' => $label]), redirect: $this->redirectUrl($request));
    }

    public function resetPlatform(Request $request): JsonResponse
    {
        $this->pages()->resetToPlatformDefault();

        return JsonResponder::success(__('content::vendor_themes.reset_platform_done'), redirect: $this->redirectUrl($request));
    }

    /**
     * Open the storefront with this theme for the vendor's browser only (signed, short-lived).
     */
    public function preview(Request $request, int $theme): RedirectResponse
    {
        $model = $this->theme($theme);
        $country = $this->pages()->scopeOrDefault($request->query('country'));
        $countryId = $country === ThemePageService::DEFAULT_SCOPE ? null : (int) $country;

        return redirect()->away($this->pages()->previewUrl($model, $model->allowsCountry($countryId) ? $countryId : null));
    }

    private function theme(int $id): Theme
    {
        $model = $this->pages()->findTheme($id);
        abort_if($model === null, 404);

        return $model;
    }

    private function redirectUrl(Request $request): string
    {
        return $this->returnTarget($request) === 'by-country'
            ? vendor_route('store.home-variants')
            : vendor_route('store.themes.index');
    }

    private function returnTarget(Request $request): string
    {
        return $request->input('return') === 'by-country' ? 'by-country' : 'themes';
    }

    private function canManage(Request $request): bool
    {
        return $this->can($request, 'store.themes.manage');
    }

    private function can(Request $request, string $permission): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can($permission);
    }
}
