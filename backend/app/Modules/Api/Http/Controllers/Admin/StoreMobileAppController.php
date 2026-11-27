<?php

namespace App\Modules\Api\Http\Controllers\Admin;

use App\Modules\Api\Http\Requests\Panel\MobileAppSettingsRequest;
use App\Modules\Api\Services\MobileAppPanelService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Vendors › profile › Mobile app tab (ADM-041g): saves one store's app settings in
 * its tenant context (vendors.manage). The tab itself is rendered by VendorProfileController.
 */
class StoreMobileAppController extends Controller
{
    public function __construct(private readonly MobileAppPanelService $panel) {}

    public function update(MobileAppSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        abort_unless($tenant->status->isOperational(), 404);

        $this->panel->save($tenant, $request->toData(...), $request->splashLogo(), $request->removesSplashLogo());

        return JsonResponder::success(__('api::panel.saved'), null, route('admin.vendors.show', ['tenant' => $tenant->id, 'tab' => 'mobile-app']));
    }
}
