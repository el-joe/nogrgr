<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Central\ChangeRegistrationEmailRequest;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Services\RegistrationFlowService;
use App\Modules\Platform\Support\RegistrationSession;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verify your e-mail (WEB-017, Figma 125:10925 · resent 125:11066 · m 125:11200):
 * resend with a 60-second cooldown (server-enforced, countdown in the page) and a send
 * limit, "Wrong e-mail? Change it". `continue` sends a visitor whose e-mail is already
 * proven (Google / Apple, signed-in owner) straight to the completion link.
 */
class RegistrationVerifyController extends Controller
{
    public function __construct(private readonly RegistrationFlowService $flow) {}

    public function show(Request $request): View|RedirectResponse
    {
        $registration = $this->flow->current(new RegistrationSession($request->session()));

        if ($redirect = $this->elsewhere($registration)) {
            return $redirect;
        }

        /** @var VendorRegistration $registration */
        return view('platform::central.signup.verify', [
            'registration' => $registration,
            'cooldown' => $this->flow->resendAvailableIn($registration),
            'resendsLeft' => $this->flow->resendsLeft($registration),
            'canChangeEmail' => $registration->owner_id === null,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $registration = $this->flow->current(new RegistrationSession($request->session()));

        if ($redirect = $this->elsewhere($registration)) {
            return $redirect;
        }

        try {
            /** @var VendorRegistration $registration */
            $this->flow->resend($registration);
        } catch (PlatformRuleException $exception) {
            return redirect()->route('central.register.verify')->with('error', $exception->getMessage());
        }

        return redirect()->route('central.register.verify')->with('resent', true);
    }

    public function changeEmail(ChangeRegistrationEmailRequest $request): RedirectResponse
    {
        $registration = $this->flow->current(new RegistrationSession($request->session()));

        if ($redirect = $this->elsewhere($registration)) {
            return $redirect;
        }

        try {
            /** @var VendorRegistration $registration */
            $this->flow->changeEmail($registration, (string) $request->validated('email'));
        } catch (PlatformRuleException $exception) {
            return redirect()->route('central.register.verify')->with('error', $exception->getMessage());
        }

        return redirect()->route('central.register.verify')->with('resent', true);
    }

    public function continue(Request $request): RedirectResponse
    {
        $registration = $this->flow->current(new RegistrationSession($request->session()));

        if ($registration === null) {
            return redirect()->route('central.register');
        }

        $url = $this->flow->continueUrl($registration);

        return $url !== null ? redirect()->to($url) : redirect()->route('central.register.verify');
    }

    /**
     * Where to go instead of the verification page (no registration, unpaid, proven e-mail).
     */
    private function elsewhere(?VendorRegistration $registration): ?RedirectResponse
    {
        return match (true) {
            $registration === null => redirect()->route('central.register'),
            in_array($registration->status, [RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed], true) => redirect()->route('central.register.payment'),
            $registration->status !== RegistrationStatus::EmailUnverified => redirect()->route('central.register.continue'),
            default => null,
        };
    }
}
