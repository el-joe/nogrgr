<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Services\VendorImpersonationService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Opens the store's vendor panel in central-host mode as the store owner.
 */
class ImpersonateVendorController extends Controller
{
    public function __construct(private readonly VendorImpersonationService $impersonation) {}

    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return redirect()->to($this->impersonation->start($admin, $tenant, $request));
    }
}
