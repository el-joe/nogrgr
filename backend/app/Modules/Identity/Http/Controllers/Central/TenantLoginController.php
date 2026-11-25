<?php

namespace App\Modules\Identity\Http\Controllers\Central;

use App\Modules\Identity\DTOs\VendorCredentialsData;
use App\Modules\Identity\Http\Requests\VendorLoginRequest;
use App\Modules\Identity\Services\VendorAuthService;
use App\Shared\Http\Controller;
use App\Shared\Routing\VendorPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Central-host vendor entry: {central}/tenant/login. Resolves the user's stores through
 * the central vendor_staff_directory; one store → straight in, several → store picker.
 */
class TenantLoginController extends Controller
{
    public function __construct(private readonly VendorAuthService $auth) {}

    public function create(Request $request): View
    {
        return view('identity::auth.login', [
            'surface' => 'vendor',
            'action' => route('central.tenant.login.store'),
            'store' => $request->query('store'),
        ]);
    }

    public function store(VendorLoginRequest $request): RedirectResponse
    {
        $stores = $this->auth->loginFromCentral(VendorCredentialsData::fromRequest($request), $request);

        if ($stores->count() === 1) {
            return redirect()->to(VendorPanel::centralUrl($stores->firstOrFail()->slug));
        }

        return redirect()->route('central.tenant.stores');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->route('central.tenant.login');
    }
}
