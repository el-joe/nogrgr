<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\Services\VendorImpersonationService;
use App\Shared\Http\Controller;
use App\Shared\Routing\VendorPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "End session" of the impersonating banner (VEN-005): audited, back to the admin panel.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly VendorImpersonationService $impersonation) {}

    public function leave(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has(VendorPanel::SESSION_IMPERSONATOR), 403);

        return redirect()->to($this->impersonation->leave($request))->with('success', __('identity::vendor_auth.impersonation.ended'));
    }
}
