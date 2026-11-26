<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate\Auth;

use App\Modules\Affiliates\Http\Requests\Portal\RegisterAffiliateRequest;
use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Affiliates\Services\AffiliateRegistrationService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Partner sign-up (AFF-002) and the "pending approval" notice shown after it (AFF-003).
 */
class RegisterController extends Controller
{
    public const SESSION_KEY = 'affiliate.registered_email';

    public function __construct(
        private readonly AffiliateRegistrationService $registration,
        private readonly AffiliateAccountService $accounts,
    ) {}

    public function create(Request $request): View
    {
        $email = $request->session()->get(self::SESSION_KEY);

        if (is_string($email)) {
            return view('affiliates::portal.auth.pending', ['surface' => 'affiliate', 'email' => $email]);
        }

        return view('affiliates::portal.auth.register', ['surface' => 'affiliate', 'commission' => $this->accounts->defaultCommissionLabel()]);
    }

    public function store(RegisterAffiliateRequest $request): RedirectResponse
    {
        $affiliate = $this->registration->register($request->toData());

        return redirect()->route('affiliate.register')->with(self::SESSION_KEY, $affiliate->email);
    }
}
