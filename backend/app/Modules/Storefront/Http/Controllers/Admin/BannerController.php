<?php

namespace App\Modules\Storefront\Http\Controllers\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Services\Promotions\PromotionPickerService;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Storefront\DataTables\BannersDataTable;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Http\Requests\Admin\BannerRequest;
use App\Modules\Storefront\Http\Requests\Admin\ReorderBannersRequest;
use App\Modules\Storefront\Http\Requests\Admin\ToggleBannerRequest;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Services\Banners\BannerService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Content › Storefront › Banners (ADM-085 / 085a, Figma 78:76059 / 78:82810):
 * default banners per country tab, drag to sort, on / off, delete; create / edit in a
 * modal (image, link, translations, dates, countries, stores).
 */
class BannerController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly BannerService $banners,
        private readonly CountryService $countries,
        private readonly CatalogLocales $locales,
        private readonly PromotionPickerService $picker,
    ) {}

    public function index(Request $request): View
    {
        $countries = $this->countries->options();
        $country = (int) $request->query('country', '0');
        $placement = BannerService::placementOrDefault($request->query('placement'));

        return view('storefront::admin.banners.index', [
            'countries' => $countries,
            'country' => array_key_exists($country, $countries) ? $country : 0,
            'placement' => $placement,
            'placements' => BannerPlacement::options(),
            'counts' => $this->banners->countryCounts(),
        ]);
    }

    public function data(Request $request, BannersDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, BannersDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        $country = (int) $request->query('country', '0');
        $banner = new Banner([
            'is_active' => true,
            'store_scope' => PromotionScope::AllStores,
            'placement' => BannerService::placementOrDefault($request->query('placement')),
        ]);

        return $this->form($request, $banner, $country > 0 ? [$country] : []);
    }

    public function store(BannerRequest $request): JsonResponse
    {
        $banner = $this->banners->create($request->toData());

        return JsonResponder::created(__('engagement::banners.created', ['title' => $banner->title ?: '#'.$banner->id]));
    }

    public function edit(Request $request, Banner $banner): View
    {
        return $this->form($request, $banner, $banner->countries->pluck('id')->map(static fn ($id): int => (int) $id)->all());
    }

    public function update(BannerRequest $request, Banner $banner): JsonResponse
    {
        $this->banners->update($banner, $request->toData());

        return JsonResponder::success(__('engagement::banners.updated', ['title' => $banner->title ?: '#'.$banner->id]));
    }

    public function toggle(ToggleBannerRequest $request, Banner $banner): JsonResponse
    {
        $this->banners->setActive($banner, $request->value());

        return JsonResponder::success(__($request->value() ? 'engagement::banners.activated' : 'engagement::banners.deactivated', ['title' => $banner->title ?: '#'.$banner->id]));
    }

    public function sort(ReorderBannersRequest $request): JsonResponse
    {
        $this->banners->reorder($request->ids());

        return JsonResponder::success(__('engagement::banners.sorted'));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $title = $banner->title ?: '#'.$banner->id;
        $this->banners->delete($banner);

        return JsonResponder::success(__('engagement::banners.deleted', ['title' => $title]));
    }

    public function stores(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->picker->stores($request->term(), $request->page()));
    }

    /**
     * @param  list<int>  $countryIds
     */
    private function form(Request $request, Banner $banner, array $countryIds): View
    {
        $title = $banner->exists ? __('engagement::banners.edit_title') : __('engagement::banners.create_title');
        $index = route('admin.storefront.banners.index');
        $banner->loadMissing('tenants:id,name');

        return $this->remoteModal($request, 'storefront::admin.banners._form', [
            'banner' => $banner,
            'title' => $title,
            'locales' => $this->locales->all(),
            'countries' => $this->countries->options(),
            'countryIds' => $countryIds,
            'scopes' => PromotionScope::options(),
            'placements' => BannerPlacement::options(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            formId: 'banner-form',
            submitLabel: __('engagement::banners.save'),
            breadcrumbs: [__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.banners') => $index, $title => null],
        ));
    }
}
