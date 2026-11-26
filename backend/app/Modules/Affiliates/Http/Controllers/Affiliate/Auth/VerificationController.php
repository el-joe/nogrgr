<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate\Auth;

use App\Modules\Affiliates\Http\Requests\Portal\ResendVerificationRequest;
use App\Modules\Affiliates\Services\AffiliateRegistrationService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * AFF-006 e-mail confirmation: the signed link's result page (confirmed / expired) and
 * "Send a new link" (same answer whether or not the e-mail is known).
 */
class VerificationController extends Controller
{
    public function __construct(private readonly AffiliateRegistrationService $registration) {}

    public function verify(Request $request, string $id, string $hash): Response
    {
        $affiliate = $this->registration->verify($request, $id, $hash);

        return response()->view('affiliates::portal.auth.verify', [
            'surface' => 'affiliate',
            'affiliate' => $affiliate,
        ], $affiliate === null ? 410 : 200);
    }

    public function resend(ResendVerificationRequest $request): RedirectResponse
    {
        $this->registration->resend($request->email());

        return back()->with('success', __('affiliates::panel.verify.resent'));
    }
}
