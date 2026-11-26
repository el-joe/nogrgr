<?php

namespace App\Modules\Storefront\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Enums\TrackingPixel;
use App\Modules\Storefront\Http\Requests\Vendor\TrackingPixelsRequest;
use App\Modules\Storefront\Services\Tracking\TrackingPixelService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Tracking pixels (VEN-083, Figma Vendor Panel 91:116736): Meta,
 * TikTok, Snap and Google Analytics 4 ids with on / off. Stored per store (tenant DB);
 * the storefront loads them only after the shopper accepts tracking (decision #91).
 */
class TrackingController extends Controller
{
    public function __construct(private readonly TrackingPixelService $tracking) {}

    public function index(Request $request): View
    {
        $staff = $request->user('vendor');

        return view('storefront::vendor.tracking.index', [
            'pixels' => TrackingPixel::cases(),
            'settings' => $this->tracking->get(),
            'canManage' => $staff instanceof VendorStaff && $staff->can('settings.tracking.manage'),
        ]);
    }

    public function update(TrackingPixelsRequest $request): JsonResponse
    {
        $this->tracking->save($request->toData());

        return JsonResponder::success(__('content::store_tracking.saved'), redirect: vendor_route('settings.tracking'));
    }
}
