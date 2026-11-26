<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate\Auth;

use App\Modules\Affiliates\Services\AffiliateSsoService;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Services\SessionAuthService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * AFF-007 landing of the vendor panel's "Open affiliate dashboard": a valid one-time
 * token signs the store's vendor affiliate in; an invalid / used / expired one shows
 * its error state (410).
 */
class SsoController extends Controller
{
    public function __construct(
        private readonly AffiliateSsoService $sso,
        private readonly SessionAuthService $auth,
    ) {}

    public function __invoke(Request $request, string $token): RedirectResponse|Response
    {
        $result = $this->sso->consume($token);

        if ($result->affiliate === null) {
            return response()->view('affiliates::portal.auth.sso', [
                'surface' => 'affiliate',
                'failure' => $result->failure,
            ], 410);
        }

        $this->auth->login(Guard::Affiliate, $result->affiliate, $request);

        return redirect()->route('affiliate.dashboard');
    }
}
