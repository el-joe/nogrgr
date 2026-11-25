<?php

namespace App\Modules\Commerce\Http\Controllers\Vendor;

use App\Modules\Commerce\DataTables\VendorCouponsDataTable;
use App\Modules\Commerce\Enums\CouponStatus;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Http\Requests\Vendor\PromotionVisibilityRequest;
use App\Modules\Commerce\Http\Requests\Vendor\StoreCouponRequest;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Services\Promotions\CentralPromotionVisibilityService;
use App\Modules\Commerce\Services\Promotions\StoreCouponService;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Ads & Marketing › Coupons (VEN-040 / 040a, Figma 87:107268 / 87:111915):
 * "My coupons" (store-funded, create / view / edit / delete in a modal) and "Nogrgr
 * coupons" (central, read-only, hide / show in this store). Runs in the store's tenant
 * context: store rows come from the tenant DB, central rows through CouponCatalog.
 */
class CouponController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly StoreCouponService $coupons,
        private readonly VendorPromotionPageService $pages,
        private readonly CentralPromotionVisibilityService $visibility,
    ) {}

    public function index(Request $request): View
    {
        return view('commerce::vendor.coupons.index', [
            'tab' => VendorPromotionPageService::tab($request->query('tab')),
            'counts' => $this->pages->couponCounts(),
            'statuses' => CouponStatus::options(),
            'countries' => $this->pages->countries(),
        ]);
    }

    public function data(Request $request, VendorCouponsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, $this->canManage($request));
    }

    public function create(Request $request): View
    {
        return $this->form($request, new StoreCoupon(['is_active' => true, 'type' => CouponType::Percentage]), true);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->toData());

        return JsonResponder::created(__('commerce::coupons.created', ['code' => $coupon->code]), redirect: vendor_route('marketing.coupons.index'));
    }

    public function show(Request $request, int $coupon): View
    {
        return $this->form($request, $this->pages->findCoupon($coupon), false);
    }

    public function edit(Request $request, int $coupon): View
    {
        return $this->form($request, $this->pages->findCoupon($coupon), true);
    }

    public function update(StoreCouponRequest $request, int $coupon): JsonResponse
    {
        $row = $this->coupons->update($this->pages->findCoupon($coupon), $request->toData());

        return JsonResponder::success(__('commerce::coupons.updated', ['code' => $row->code]));
    }

    public function destroy(int $coupon): JsonResponse
    {
        $row = $this->pages->findCoupon($coupon);
        $this->coupons->delete($row);

        return JsonResponder::success(__('commerce::coupons.deleted', ['code' => $row->code]));
    }

    public function visibility(PromotionVisibilityRequest $request, int $coupon): JsonResponse
    {
        $central = $this->pages->centralCoupon($coupon);
        abort_if($central === null, 404);

        $this->visibility->setHidden(PromotionKind::Coupon, $central->id, $request->hidden());

        return JsonResponder::success(__($request->hidden() ? 'commerce::vendor_promotions.hidden_done' : 'commerce::vendor_promotions.shown_done', ['name' => $central->code]));
    }

    private function form(Request $request, StoreCoupon $coupon, bool $editable): View
    {
        $title = match (true) {
            ! $coupon->exists => __('commerce::coupons.create_title'),
            $editable => __('commerce::coupons.edit_title', ['code' => $coupon->code]),
            default => $coupon->code,
        };
        $index = vendor_route('marketing.coupons.index');
        $coupon->loadMissing('countryRows');

        return $this->remoteModal($request, 'commerce::vendor.coupons._form', [
            'coupon' => $coupon,
            'title' => $title,
            'editable' => $editable,
            'usage' => $coupon->exists ? $this->pages->couponUsage($coupon) : 0,
            'countries' => $this->pages->countries(),
            'types' => CouponType::options(),
        ], new RemoteModalPage(
            surface: 'vendor',
            title: $title,
            backUrl: $index,
            pageModule: 'vendor-catalog',
            formId: $editable ? 'vendor-coupon-form' : null,
            submitLabel: __('commerce::promotions.save'),
            breadcrumbs: [__('commerce::vendor_promotions.section') => null, __('commerce::coupons.title') => $index, $title => null],
        ));
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('coupons.manage');
    }
}
