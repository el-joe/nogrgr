<?php

namespace App\Modules\Identity\Http\Controllers\Central;

use App\Modules\Identity\Exceptions\ImpersonationLinkExpiredException;
use App\Modules\Identity\Services\VendorImpersonationService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * Impersonation accept link (VEN-005): `{central}/tenant/impersonate/{token}`, signed and
 * single use. Opens the store panel as its owner, or shows the expired-link state.
 */
class ImpersonationAcceptController extends Controller
{
    public function __construct(private readonly VendorImpersonationService $impersonation) {}

    public function __invoke(Request $request, string $token): RedirectResponse|Response
    {
        try {
            if (! $request->hasValidSignature()) {
                throw new ImpersonationLinkExpiredException;
            }

            return redirect()->to($this->impersonation->accept($token, $request));
        } catch (ImpersonationLinkExpiredException) {
            return response()->view('identity::vendor.impersonation-expired', [
                'adminUrl' => Route::has('admin.vendors.index') ? route('admin.vendors.index') : null,
            ], 410);
        }
    }
}
