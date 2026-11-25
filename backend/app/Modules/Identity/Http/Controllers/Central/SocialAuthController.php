<?php

namespace App\Modules\Identity\Http\Controllers\Central;

use App\Modules\Identity\Enums\SocialIntent;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Exceptions\SocialAuthException;
use App\Modules\Identity\Services\OwnerService;
use App\Modules\Identity\Services\SocialAuthService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Google / Apple for store owners: {central}/auth/{provider}/redirect and the callback
 * registered in the provider consoles ({central}/auth/{provider}/callback, GET for
 * Google, POST form_post for Apple). The callback renders the WEB-016a result
 * (Figma 125:10900 success, 125:10912 cancelled): success continues automatically to
 * the sign-up plan step (register) or the store picker (login). A provider that is not
 * enabled in Integrations answers 404.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $social,
        private readonly OwnerService $owners,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $social = $this->enabled($provider);
        $intent = SocialIntent::tryFrom((string) $request->query('intent', '')) ?? SocialIntent::Login;

        return redirect()->away($this->social->begin($social, $intent, $request));
    }

    public function callback(Request $request, string $provider): View
    {
        $social = $this->enabled($provider);
        $handoff = $request->query('handoff');
        $parameters = is_string($handoff) && $handoff !== '' ? $this->social->pullHandoff($handoff) : $request->query();
        $intent = $this->social->pendingIntent($request);

        try {
            $result = $this->social->complete($social, $parameters, $request);
        } catch (SocialAuthException $exception) {
            if (! $exception->cancelled) {
                report($exception);
            }

            return view('identity::social.result', [
                'provider' => $social,
                'state' => $exception->cancelled ? 'cancelled' : 'error',
                'message' => $exception->userMessage,
                'continueUrl' => match ($intent) {
                    SocialIntent::Login => route('central.tenant.login'),
                    SocialIntent::Link => route('owner.profile.edit'),
                    SocialIntent::Register => route('central.register'),
                },
            ]);
        }

        return view('identity::social.result', [
            'provider' => $social,
            'state' => 'success',
            'message' => null,
            'continueUrl' => $this->continueUrl($result['intent'], $social),
        ]);
    }

    /**
     * Apple's form_post arrives cross-site without the session cookie: park the
     * parameters and continue on the GET callback (303) where the session is back.
     */
    public function callbackPost(Request $request, string $provider): RedirectResponse
    {
        $social = $this->enabled($provider);
        $key = $this->social->handoff($request->only(['code', 'state', 'error', 'user']));

        return redirect()->route('central.social.callback', ['provider' => $social->value, 'handoff' => $key], 303);
    }

    private function continueUrl(SocialIntent $intent, SocialProvider $provider): string
    {
        if ($intent === SocialIntent::Link) {
            return route('owner.profile.edit');
        }

        $owner = $this->owners->current();

        if ($intent === SocialIntent::Login && $owner !== null && $this->owners->stores($owner)->isNotEmpty()) {
            return route('central.tenant.stores');
        }

        return route('central.register.social', ['provider' => $provider->value]);
    }

    private function enabled(string $provider): SocialProvider
    {
        $social = SocialProvider::tryFrom($provider);

        abort_if($social === null || ! $social->isEnabled(), 404);

        return $social;
    }
}
