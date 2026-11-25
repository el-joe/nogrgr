<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Modules\Commerce\DataTables\CouponsDataTable;
use App\Modules\Commerce\Enums\CouponStatus;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Http\Requests\Admin\CouponRequest;
use App\Modules\Commerce\Http\Requests\Admin\PickerSearchRequest;
use App\Modules\Commerce\Http\Requests\Admin\ToggleActiveRequest;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Services\Promotions\CouponService;
use App\Modules\Commerce\Services\Promotions\PromotionPickerService;
use App\Modules\Localization\Services\CountryService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Sales › Promotions › Coupons (ADM-025 / 025a, Figma 70:39532 / 70:41386):
 * country tabs + server-side list; view / create / edit in a modal; active switch;
 * delete. Central coupons come off the platform share; stores read them live.
 */
class CouponController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly CouponService $coupons,
        private readonly PromotionPickerService $picker,
        private readonly CountryService $countries,
    ) {}

    public function index(): View
    {
        return view('commerce::admin.coupons.index', [
            'tabs' => $this->coupons->countryTabs(),
            'statuses' => CouponStatus::options(),
            'types' => CouponType::options(),
        ]);
    }

    public function data(Request $request, CouponsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, CouponsDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Coupon(['is_active' => true, 'type' => CouponType::Percentage, 'store_scope' => PromotionScope::AllStores]), true);
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->toData());

        return JsonResponder::created(__('commerce::coupons.created', ['code' => $coupon->code]), redirect: route('admin.promotions.coupons.index'));
    }

    public function show(Request $request, Coupon $coupon): View
    {
        return $this->form($request, $coupon, false);
    }

    public function edit(Request $request, Coupon $coupon): View
    {
        return $this->form($request, $coupon, true);
    }

    public function update(CouponRequest $request, Coupon $coupon): JsonResponse
    {
        $this->coupons->update($coupon, $request->toData());

        return JsonResponder::success(__('commerce::coupons.updated', ['code' => $coupon->code]));
    }

    public function toggle(ToggleActiveRequest $request, Coupon $coupon): JsonResponse
    {
        $this->coupons->setActive($coupon, $request->value());

        return JsonResponder::success(__($request->value() ? 'commerce::coupons.activated' : 'commerce::coupons.deactivated', ['code' => $coupon->code]));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->coupons->delete($coupon);

        return JsonResponder::success(__('commerce::coupons.deleted', ['code' => $coupon->code]));
    }

    public function stores(PickerSearchRequest $request): JsonResponse
    {
        return response()->json($this->picker->stores($request->term(), $request->page()));
    }

    private function form(Request $request, Coupon $coupon, bool $editable): View
    {
        $title = match (true) {
            ! $coupon->exists => __('commerce::coupons.create_title'),
            $editable => __('commerce::coupons.edit_title', ['code' => $coupon->code]),
            default => $coupon->code,
        };
        $index = route('admin.promotions.coupons.index');
        $coupon->loadMissing(['countries:id', 'tenants:id,name']);

        return $this->remoteModal($request, 'commerce::admin.coupons._form', [
            'coupon' => $coupon,
            'title' => $title,
            'editable' => $editable,
            'usage' => $coupon->exists ? $this->coupons->usage($coupon) : 0,
            'countries' => $this->countries->options(),
            'types' => CouponType::options(),
            'scopes' => PromotionScope::options(),
        ], new RemoteModalPage(
            surface: 'admin',
            title: $title,
            backUrl: $index,
            formId: $editable ? 'coupon-form' : null,
            submitLabel: __('commerce::promotions.save'),
            breadcrumbs: [__('navigation.sections.sales') => null, __('navigation.admin.promotions') => null, __('commerce::coupons.title') => $index, $title => null],
        ));
    }
}
