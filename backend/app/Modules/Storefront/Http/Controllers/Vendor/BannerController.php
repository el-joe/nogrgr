<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\DataTables\VendorBannersDataTable;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Http\Requests\Vendor\BannerVisibilityRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ReorderStoreBannersRequest;
use App\Modules\Storefront\Http\Requests\Vendor\StoreBannerRequest;
use App\Modules\Storefront\Http\Requests\Vendor\ToggleStoreBannerRequest;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Services\Banners\BannerService;
use App\Modules\Storefront\Services\Banners\StoreBannerService;
use App\Modules\Storefront\Services\Banners\VendorBannerPageService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Your store › Banners (VEN-035 / 035a, Figma Vendor Panel 85:108271 /
 * 85:110108): country tabs (selling countries), the store's banners merged with the
 * platform's default banners per placement, drag to sort the store's own, on / off,
 * show / hide platform banners (one or all), plan quota; create / edit in a modal.
 * Runs in the store's tenant context (store rows: tenant DB; platform rows: central DB).
 */
class BannerController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly VendorBannerPageService $pages,
        private readonly StoreBannerService $banners,
        private readonly CatalogLocales $locales,
    ) {}

    public function index(Request $request): View
    {
        $countries = $this->pages->countries();
        $country = VendorBannerPageService::countryOrDefault($request->query('country'), $countries);

        return view('storefront::vendor.banners.index', [
            'countries' => $countries,
            'country' => $country,
            'counts' => $this->pages->counts(array_keys($countries)),
            'placement' => BannerService::placementOrDefault($request->query('placement')),
            'placements' => BannerPlacement::options(),
            'quota' => $this->pages->quota(),
            'defaultsVisible' => $this->pages->defaultsVisible(),
            'hasCentral' => $this->pages->hasCentralBanners(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function data(Request $request, VendorBannersDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, $this->canManage($request));
    }

    public function create(Request $request): View
    {
        $country = filter_var($request->query('country'), FILTER_VALIDATE_INT);
        $banner = new StoreBanner([
            'is_active' => true,
            'placement' => BannerService::placementOrDefault($request->query('placement')),
        ]);

        return $this->form($request, $banner, is_int($country) && array_key_exists($country, $this->pages->countries()) ? [$country] : []);
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $banner = $this->pages->create($request->toData());

        return JsonResponder::created(__('engagement::banners.created', ['title' => $banner->title ?: '#'.$banner->id]));
    }

    public function edit(Request $request, int $banner): View
    {
        $row = $this->pages->findOwn($banner);

        return $this->form($request, $row, $row->countryIds());
    }

    public function update(StoreBannerRequest $request, int $banner): JsonResponse
    {
        $row = $this->banners->update($this->pages->findOwn($banner), $request->toData());

        return JsonResponder::success(__('engagement::banners.updated', ['title' => $row->title ?: '#'.$row->id]));
    }

    public function toggle(ToggleStoreBannerRequest $request, int $banner): JsonResponse
    {
        $row = $this->pages->findOwn($banner);
        $this->banners->setActive($row, $request->value());

        return JsonResponder::success(__($request->value() ? 'engagement::banners.activated' : 'engagement::banners.deactivated', ['title' => $row->title ?: '#'.$row->id]));
    }

    public function sort(ReorderStoreBannersRequest $request): JsonResponse
    {
        $this->banners->reorder($request->ids());

        return JsonResponder::success(__('engagement::banners.sorted'));
    }

    public function destroy(int $banner): JsonResponse
    {
        $row = $this->pages->findOwn($banner);
        $title = $row->title ?: '#'.$row->id;
        $this->banners->delete($row);

        return JsonResponder::success(__('engagement::banners.deleted', ['title' => $title]));
    }

    /**
     * Show / hide one platform banner on this store (the central row is untouched).
     */
    public function visibility(BannerVisibilityRequest $request, int $banner): JsonResponse
    {
        $central = $this->pages->findCentral($banner);
        $this->banners->setCentralHidden($central->id, ! $request->visible());

        return JsonResponder::success(__($request->visible() ? 'engagement::vendor_banners.shown' : 'engagement::vendor_banners.hidden', ['title' => $central->title ?: '#'.$central->id]));
    }

    /**
     * "Show platform default banners" switch: shows / hides every platform banner.
     */
    public function defaults(BannerVisibilityRequest $request): JsonResponse
    {
        $this->pages->setDefaultsVisible($request->visible());

        return JsonResponder::success(__($request->visible() ? 'engagement::vendor_banners.defaults_shown' : 'engagement::vendor_banners.defaults_hidden'));
    }

    /**
     * @param  list<int>  $countryIds
     */
    private function form(Request $request, StoreBanner $banner, array $countryIds): View
    {
        $title = $banner->exists ? __('engagement::banners.edit_title') : __('engagement::vendor_banners.create_title');
        $index = vendor_route('store.banners.index');

        return $this->remoteModal($request, 'storefront::vendor.banners._form', [
            'banner' => $banner,
            'title' => $title,
            'locales' => $this->locales->all(),
            'countries' => $this->pages->countries(),
            'countryIds' => $countryIds,
            'placements' => BannerPlacement::options(),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-banners',
            formId: 'vendor-banner-form',
            submitLabel: __('engagement::banners.save'),
            breadcrumbs: [__('navigation.vendor.store_design') => null, __('navigation.vendor.banners') => $index, $title => null],
        ));
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('store.banners.manage');
    }
}
