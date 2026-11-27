<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Billing\Services\PlanLimitOverrideService;
use App\Modules\Platform\Http\Requests\Admin\VendorLimitOverridesRequest;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Vendors › Vendor profile › Limit overrides (ADM-041h): saves the store's
 * per-limit overrides (vendors.limits.manage). The tab itself is rendered by
 * VendorProfileController (?tab=limits, vendors.view).
 */
class VendorLimitOverrideController extends Controller
{
    public function __construct(private readonly PlanLimitOverrideService $overrides) {}

    public function update(VendorLimitOverridesRequest $request, Tenant $tenant): JsonResponse
    {
        $this->overrides->save($tenant, $request->toData());

        return JsonResponder::success(__('platform::vendors.limits.updated'), null, route('admin.vendors.show', ['tenant' => $tenant->id, 'tab' => 'limits']));
    }
}
