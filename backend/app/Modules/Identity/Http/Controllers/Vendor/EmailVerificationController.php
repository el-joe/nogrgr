<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Services\VendorEmailVerificationService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vendor e-mail verification (VEN-004, Figma 82:99909): the signed link's result page
 * (verified / expired) and "Send a new link" for a signed-in member.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly VendorEmailVerificationService $verification) {}

    public function verify(Request $request, string $id, string $hash): Response
    {
        $staff = $this->verification->verify($request, $id, $hash);
        $signedIn = $request->user('vendor');

        return response()->view('identity::vendor.verify-result', [
            'staff' => $staff,
            'continueUrl' => $signedIn instanceof VendorStaff ? vendor_route('onboarding') : vendor_route('login'),
            'resendUrl' => $signedIn instanceof VendorStaff && ! $signedIn->hasVerifiedEmail() ? vendor_route('verification.send') : null,
            'loginUrl' => vendor_route('login'),
        ], $staff === null ? 410 : 200);
    }

    public function send(Request $request): RedirectResponse
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');
        $sent = $this->verification->resend($staff);

        return back()->with($sent ? 'success' : 'info', __($sent ? 'identity::vendor_auth.verify.sent' : 'identity::vendor_auth.verify.already'));
    }
}
