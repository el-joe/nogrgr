<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\DTOs\ImpersonationData;
use App\Modules\Platform\Http\Requests\Admin\ImpersonateVendorRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\VendorService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Vendor profile › Impersonate (ADM-041i): records the reason in the audit log, signs
 * the admin in as the store owner and answers {redirect: vendor panel URL}; the page
 * opens it. Permission: tenants.impersonate.
 */
class VendorImpersonationController extends Controller
{
    public function __construct(private readonly VendorService $vendors) {}

    public function __invoke(ImpersonateVendorRequest $request, Tenant $tenant): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $url = $this->vendors->impersonate($admin, $tenant, ImpersonationData::fromRequest($request), $request);

        return JsonResponder::success(__('platform::vendors.impersonate.started', ['name' => $tenant->name]), null, $url);
    }
}
