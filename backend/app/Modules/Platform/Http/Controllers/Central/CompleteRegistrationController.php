<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Platform\DTOs\RegistrationAccountData;
use App\Modules\Platform\DTOs\RegistrationStoreData;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Central\CompleteRegistrationAccountRequest;
use App\Modules\Platform\Http\Requests\Central\CompleteRegistrationStoreRequest;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Services\VendorRegistrationService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Complete registration (WEB-018 account step → WEB-018a store step) behind the
 * e-mailed signed link. A tampered link or an unknown token renders the "invalid"
 * state, an expired link the "expired" one and a finished sign-up the "used" one.
 * The POST steps re-check the token (it travels in the body) before saving.
 */
class CompleteRegistrationController extends Controller
{
    /** Query keys that may be added to the signed link without breaking it. */
    private const UNSIGNED_QUERY = ['lang', 'step'];

    public function __construct(private readonly VendorRegistrationService $registrations) {}

    public function show(Request $request): View
    {
        $token = (string) $request->query('token', '');

        if (! URL::hasCorrectSignature($request, true, self::UNSIGNED_QUERY)) {
            return $this->state('invalid');
        }

        $registration = $this->registrations->resolveToken($token);

        if ($registration === null) {
            return $this->state('invalid');
        }

        if ($registration->status === RegistrationStatus::Completed) {
            return $this->state('used', $registration);
        }

        if ($registration->isExpired() || ! URL::signatureHasNotExpired($request)) {
            return $this->state('expired', $registration);
        }

        try {
            $this->registrations->open($registration);
        } catch (PlatformRuleException) {
            return $this->state('expired', $registration);
        }

        $accountSaved = $registration->owner_name !== null && $registration->password !== null;

        if ($accountSaved && $request->query('step') !== 'account') {
            return view('platform::central.register.store', [
                'registration' => $registration,
                'token' => $token,
                'accountUrl' => $request->fullUrlWithQuery(['step' => 'account']),
                'baseDomain' => (string) config('tenancy.base_domain'),
            ]);
        }

        return view('platform::central.register.account', [
            'registration' => $registration,
            'token' => $token,
            'accountSaved' => $accountSaved,
        ]);
    }

    public function account(CompleteRegistrationAccountRequest $request): RedirectResponse
    {
        $token = (string) $request->validated('token');
        $registration = $this->registrations->resolveToken($token);

        if ($registration === null) {
            return redirect()->route('central.register.complete', ['token' => $token]);
        }

        try {
            $this->registrations->saveAccount($registration, RegistrationAccountData::fromRequest($request));
        } catch (PlatformRuleException) {
            return redirect()->to($this->linkFor($registration, $token));
        }

        return redirect()->to($this->linkFor($registration, $token))
            ->with('success', __('platform::register.account.saved'));
    }

    public function store(CompleteRegistrationStoreRequest $request): RedirectResponse
    {
        $token = (string) $request->validated('token');
        $registration = $this->registrations->resolveToken($token);

        if ($registration === null) {
            return redirect()->route('central.register.complete', ['token' => $token]);
        }

        if ($registration->status === RegistrationStatus::Completed || $registration->isExpired()) {
            return redirect()->to($this->linkFor($registration, $token));
        }

        try {
            $this->registrations->complete($registration, new RegistrationStoreData(
                storeName: (string) $request->validated('store_name'),
                slug: (string) $request->validated('slug'),
                customDomain: $request->validated('custom_domain') ?: null,
                profitPercentBps: $request->profitPercentBps(),
            ));
        } catch (PlatformRuleException $exception) {
            return back()->withInput($request->except('token'))->with('error', $exception->getMessage());
        }

        return redirect()->to($this->registrations->provisioningUrl($registration));
    }

    private function state(string $state, ?VendorRegistration $registration = null): View
    {
        return view('platform::central.register.link-state', [
            'state' => $state,
            'registration' => $registration,
            'hours' => $this->registrations->linkLifetimeHours(),
        ]);
    }

    /**
     * The completion link for this token again (lands on the right step or state).
     */
    private function linkFor(VendorRegistration $registration, string $token): string
    {
        return $this->registrations->completionUrl($token, $registration->expires_at ?? now()->addHours($this->registrations->linkLifetimeHours()));
    }
}
