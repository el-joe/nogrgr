<?php

namespace App\Modules\Api\Http\Controllers\Vendor;

use App\Modules\Api\Http\Requests\Panel\MobileAppSettingsRequest;
use App\Modules\Api\Services\MobileAppPanelService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Your store › Mobile app (VEN-037 / 037a, Figma 200:37622): versions and store
 * links, maintenance, splash, features of the store app. store.mobile-app.view reads,
 * store.mobile-app.manage saves.
 */
class MobileAppController extends Controller
{
    public function __construct(private readonly MobileAppPanelService $panel) {}

    public function show(Request $request): View
    {
        $staff = $request->user('vendor');

        return view('api::panel.vendor', [
            'page' => $this->panel->page(),
            'canManage' => $staff instanceof VendorStaff && $staff->can('store.mobile-app.manage'),
        ]);
    }

    public function update(MobileAppSettingsRequest $request): JsonResponse
    {
        $this->panel->save(null, $request->toData(...), $request->splashLogo(), $request->removesSplashLogo());

        return JsonResponder::success(__('api::panel.saved'), null, vendor_route('store.mobile-app'));
    }
}
