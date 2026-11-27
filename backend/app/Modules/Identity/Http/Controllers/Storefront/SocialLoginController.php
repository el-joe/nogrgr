<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\Enums\SocialIntent;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Services\CustomerAuthService;
use App\Modules\Identity\Services\CustomerSocialAuthService;
use App\Modules\Identity\Support\Social\CustomerSocialHandoff;
use App\Shared\Http\Controller;
use App\Shared\Http\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Theme "Continue with Google / Apple" ({store}/account/social/…): the store host seals
 * which store and where to return, the platform host runs the provider flow on its
 * registered callback and hands the verified profile back (CustomerSocialHandoff); the
 * store finds / links / creates the customer and signs in. Providers not configured in
 * Admin › Integrations → 404 (no button is shown).
 */
class SocialLoginController extends Controller
{
    public function __construct(
        private readonly CustomerSocialAuthService $social,
        private readonly CustomerAuthService $auth,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $social = SocialProvider::tryFrom($provider);
        abort_if($social === null || ! $social->isEnabled(), 404);

        $payload = CustomerSocialHandoff::start((int) tenant()?->getTenantKey(), $request->getSchemeAndHttpHost(), SafeRedirect::target($request->query('redirect'), $request));

        return redirect()->away(route('central.social.redirect', ['provider' => $social->value, 'intent' => SocialIntent::Customer->value, 'payload' => $payload]));
    }

    public function complete(Request $request): RedirectResponse
    {
        $result = CustomerSocialHandoff::readResult($request->query('token'), (int) tenant()?->getTenantKey());
        $login = route('storefront.account.login');

        if ($result === null) {
            return redirect()->to($login)->with('social_error', __('identity::auth.social.generic'));
        }

        if ($result['profile'] === null) {
            return $result['error'] === 'cancelled'
                ? redirect()->to($login)
                : redirect()->to($login)->with('social_error', __('identity::auth.social.generic'));
        }

        try {
            $customer = $this->social->resolve($result['profile'])['customer'];
        } catch (ValidationException $exception) {
            return redirect()->to($login)->with('social_error', $exception->validator->errors()->first());
        }

        $this->auth->loginVerified($customer, $request);

        return redirect()->to(SafeRedirect::target($result['redirect'], $request) ?? route('storefront.home'));
    }
}
