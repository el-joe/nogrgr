<?php

namespace App\Modules\Platform\Http\Controllers\Owner;

use App\Modules\Identity\Services\VendorAuthService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owner portal sign out: ends the owner session and any store session opened from it.
 */
class OwnerSessionController extends Controller
{
    public function __construct(private readonly VendorAuthService $auth) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->route('central.tenant.login');
    }
}
