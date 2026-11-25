<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Services\OwnerService;
use App\Modules\Platform\DTOs\SignUpDraftData;
use App\Modules\Platform\Http\Requests\Central\SignUpRequest;
use App\Modules\Platform\Services\RegistrationFlowService;
use App\Modules\Platform\Support\RegistrationSession;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sign up (WEB-016, Figma 12:42223 / m 12:43467): e-mail, phone, compliance accept and
 * "Sign up with Google / Apple" (only enabled providers are shown). A signed-in owner
 * signs up with the owner's e-mail (already proven). Nothing is stored before the plan
 * step; the form lives in the session draft.
 */
class SignUpController extends Controller
{
    public function __construct(
        private readonly RegistrationFlowService $flow,
        private readonly OwnerService $owners,
    ) {}

    public function show(Request $request): View
    {
        $session = new RegistrationSession($request->session());
        $planId = filter_var($request->query('plan'), FILTER_VALIDATE_INT);
        $term = BillingTerm::tryFrom((string) $request->query('term', ''));

        if ($planId !== false && $planId !== null) {
            $session->putPreselectedPlan(['plan' => $planId, 'term' => ($term ?? BillingTerm::Monthly)->value]);
        }

        return view('platform::central.signup.sign-up', [
            'draft' => $session->draft(),
            'owner' => $this->owners->current(),
            'providers' => SocialProvider::enabled(),
            'socialError' => $request->session()->get('social_error'),
        ]);
    }

    public function store(SignUpRequest $request): RedirectResponse
    {
        $session = new RegistrationSession($request->session());
        $owner = $this->owners->current();
        $phone = (string) $request->validated('phone');
        $acceptedAt = now()->toIso8601String();
        $provider = $owner === null ? $request->socialProvider() : null;

        if ($provider !== null) {
            $session->putSocialPending(['phone' => $phone, 'compliance_accepted_at' => $acceptedAt, 'locale' => app()->getLocale()]);

            return redirect()->route('central.social.redirect', ['provider' => $provider->value, 'intent' => 'register']);
        }

        $this->flow->start($session, new SignUpDraftData(
            email: $owner?->email ?? (string) $request->validated('email'),
            phone: $phone,
            locale: app()->getLocale(),
            complianceAcceptedAt: $acceptedAt,
            emailVerified: $owner !== null,
            ownerId: $owner?->id,
            ownerName: $owner?->name,
        ));

        return redirect()->route('central.register.plan');
    }

    /**
     * Back from Google / Apple (the owner guard is signed in by the social callback).
     */
    public function social(Request $request): RedirectResponse
    {
        $session = new RegistrationSession($request->session());
        $owner = $this->owners->current();
        $pending = $session->pullSocialPending();
        $provider = SocialProvider::tryFrom((string) $request->query('provider', ''));

        if ($owner === null || $pending === null) {
            return redirect()->route('central.register');
        }

        $this->flow->start($session, new SignUpDraftData(
            email: $owner->email,
            phone: $owner->phone ?? $pending['phone'],
            locale: $pending['locale'],
            complianceAcceptedAt: $pending['compliance_accepted_at'],
            emailVerified: $owner->email_verified_at !== null,
            ownerId: $owner->id,
            ownerName: $owner->name,
            socialProvider: $provider?->value,
        ));

        return redirect()->route('central.register.plan');
    }
}
