<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Services\VendorService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Store lifecycle actions from the vendors list and profile: activate, suspend, retry
 * provisioning (vendors.manage) and delete, which drops the store database (vendors.delete).
 * Refused transitions answer 422 through PlatformRuleException.
 */
class VendorStatusController extends Controller
{
    public function __construct(
        private readonly VendorService $vendors,
        private readonly TenantProvisioningService $provisioning,
    ) {}

    public function activate(Tenant $tenant): JsonResponse
    {
        $this->vendors->activate($tenant);

        return JsonResponder::success(__('platform::vendors.actions.activated', ['name' => $tenant->name]));
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        $this->vendors->suspend($tenant);

        return JsonResponder::success(__('platform::vendors.actions.suspended', ['name' => $tenant->name]));
    }

    public function retry(Tenant $tenant): JsonResponse
    {
        $this->provisioning->retry($tenant);

        return JsonResponder::success(__('platform::vendors.actions.retried', ['name' => $tenant->name]));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $name = $tenant->name;
        $this->vendors->delete($tenant);

        return JsonResponder::success(__('platform::vendors.actions.deleted', ['name' => $name]), null, route('admin.vendors.index'));
    }
}
