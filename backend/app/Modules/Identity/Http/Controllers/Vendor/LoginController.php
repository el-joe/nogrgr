<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DTOs\VendorCredentialsData;
use App\Modules\Identity\Http\Requests\VendorLoginRequest;
use App\Modules\Identity\Services\VendorAuthService;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Store-host vendor sign-in (`{store-domain}/login`, VEN-001, Figma 82:99816) and
 * sign-out for both host modes. Validation in VendorLoginRequest; "remember me" and
 * the per e-mail + IP throttle live in VendorAuthService.
 */
class LoginController extends Controller
{
    public function __construct(private readonly VendorAuthService $auth) {}

    public function create(): View
    {
        $tenant = tenant();

        return view('identity::vendor.login', [
            'action' => vendor_route('login.store'),
            'storeName' => $tenant instanceof Tenant ? $tenant->name : null,
            'panelHost' => request()->getHost().'/admin',
        ]);
    }

    public function store(VendorLoginRequest $request): RedirectResponse
    {
        $this->auth->loginToCurrentTenant(VendorCredentialsData::fromRequest($request), $request);

        return redirect()->intended(vendor_route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->to(vendor_route('login'));
    }
}
