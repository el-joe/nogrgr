<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Http\Requests\Vendor\ThemeActivationRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ThemeVariantRequest;
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
 * Vendor › Your store › Themes (VEN-030, Figma Vendor Panel 85:104756) and Home variants
 * (VEN-031): pick the store default theme and a theme per selling country, the home
 * variant of the active family, "Use … in" countries modal (VEN-030a) and the country
 * home-variant modal (VEN-030b, 85:110108), signed storefront preview. Writes go
 * through StoreThemeService only (decision #76); runs in the store's tenant context.
 */
class ThemeController extends Controller
{
    use RendersRemoteModals;

    public function __construct(private readonly ThemePageService $pages) {}

    public function index(Request $request): View
    {
        $scope = $this->pages->scopeOrDefault($request->query('country'));
        $row = $this->pages->row($scope);

        return view('storefront::vendor.themes.index', [
            'scope' => $scope,
            'row' => $row,
            'countries' => $this->pages->countries(),
            'families' => $this->pages->families(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function homeVariants(Request $request): View
    {
        return view('storefront::vendor.themes.home-variants', [
            'rows' => $this->pages->rows(),
            'hasCatalogue' => $this->pages->catalogue()->isNotEmpty(),
            'canManage' => $this->canManage($request),
        ]);
    }

    /**
     * VEN-030a: "Use <theme> in…" (countries + store default).
     */
    public function countries(Request $request, string $theme): View
    {
        $model = $this->pages->findTheme($theme);
        abort_if($model === null, 404);

        $title = __('content::vendor_themes.use_in', ['theme' => $model->name]);
        $index = vendor_route('store.themes.index');

        return $this->remoteModal($request, 'storefront::vendor.themes._countries-form', [
            'theme' => $model,
            'title' => $title,
            'countries' => $this->pages->countries(),
            'selected' => $this->pages->countriesUsing($model->slug),
            'isDefault' => $this->pages->row(ThemePageService::DEFAULT_SCOPE)->theme?->slug === $model->slug,
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
        $data = $request->toData();
        $theme = $this->pages->activate($data);
        $scope = $data->asDefault || $data->countryIds === [] ? ThemePageService::DEFAULT_SCOPE : (string) $data->countryIds[0];

        return JsonResponder::success(
            __('content::vendor_themes.activated', ['theme' => $theme->name]),
            redirect: $this->redirectUrl($request, $request->input('scope', $scope)),
        );
    }

    /**
     * VEN-030b: home variant of one scope (store default or a country).
     */
    public function variant(Request $request, string $scope): View
    {
        $row = $this->pages->row($scope);
        abort_if($row->theme === null, 404);

        $title = __('content::vendor_themes.variant_title', ['scope' => $row->label]);
        $index = vendor_route('store.home-variants');

        return $this->remoteModal($request, 'storefront::vendor.themes._variant-form', [
            'row' => $row,
            'title' => $title,
            'options' => $this->pages->variantOptions($scope),
            'return' => $this->returnTarget($request),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-themes',
            formId: 'vendor-theme-variant-form',
            submitLabel: __('content::vendor_themes.save'),
            breadcrumbs: [__('navigation.vendor.store_design') => null, __('navigation.vendor.home_variants') => $index, $title => null],
        ));
    }

    public function updateVariant(ThemeVariantRequest $request, string $scope): JsonResponse
    {
        $theme = $this->pages->setVariant($scope, $request->variant());

        return JsonResponder::success(
            __('content::vendor_themes.variant_saved', ['variant' => $theme->variant, 'theme' => $theme->name]),
            redirect: $this->redirectUrl($request, $scope),
        );
    }

    public function reset(Request $request, string $scope): JsonResponse
    {
        $label = $this->pages->row($scope)->label;
        $this->pages->resetCountry($scope);

        return JsonResponder::success(__('content::vendor_themes.reset_done', ['country' => $label]), redirect: $this->redirectUrl($request, $scope));
    }

    /**
     * Open the storefront with this theme for the vendor's browser only (signed, short-lived).
     */
    public function preview(Request $request, string $theme): RedirectResponse
    {
        $model = $this->pages->findTheme($theme);
        abort_if($model === null, 404);

        $country = $this->pages->scopeOrDefault($request->query('country'));

        return redirect()->away($this->pages->previewUrl($model, $country === ThemePageService::DEFAULT_SCOPE ? null : (int) $country));
    }

    private function redirectUrl(Request $request, mixed $scope): string
    {
        if ($this->returnTarget($request) === 'home-variants') {
            return vendor_route('store.home-variants');
        }

        $scope = $this->pages->scopeOrDefault($scope);

        return vendor_route('store.themes.index', $scope === ThemePageService::DEFAULT_SCOPE ? [] : ['country' => $scope]);
    }

    private function returnTarget(Request $request): string
    {
        return $request->input('return') === 'home-variants' ? 'home-variants' : 'themes';
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('store.themes.manage');
    }
}
