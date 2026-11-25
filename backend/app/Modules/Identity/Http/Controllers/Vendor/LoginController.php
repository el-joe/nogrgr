<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DTOs\VendorCredentialsData;
use App\Modules\Identity\Http\Requests\VendorLoginRequest;
use App\Modules\Identity\Services\VendorAuthService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Store-host vendor sign-in (`{store-domain}/login`) and sign-out for both host modes.
 */
class LoginController extends Controller
{
    public function __construct(private readonly VendorAuthService $auth) {}

    public function create(): View
    {
        return view('identity::auth.login', [
            'surface' => 'vendor',
            'action' => vendor_route('login.store'),
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
