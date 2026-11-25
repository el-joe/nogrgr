<?php

namespace App\Modules\Identity\Http\Controllers\Central;

use App\Modules\Content\Support\CentralSeo;
use App\Modules\Identity\Http\Requests\Central\StoreLoginForgotRequest;
use App\Modules\Identity\Http\Requests\Central\StoreLoginResetRequest;
use App\Modules\Identity\Services\StoreLoginPasswordResetService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Central store login › forgot password (WEB-023, Figma 127:12477 / sent 127:12646) and
 * reset password (WEB-024, 127:12754 / invalid token 127:12867).
 */
class StoreLoginPasswordController extends Controller
{
    public function __construct(
        private readonly StoreLoginPasswordResetService $resets,
        private readonly CentralSeo $seo,
    ) {}

    public function request(): View
    {
        return view('content::central.auth.forgot-password', [
            'seo' => $this->seo->make(__('identity::store_login.forgot_title'), __('identity::store_login.forgot_description'), noindex: true),
            'action' => route('central.password.email'),
        ]);
    }

    public function email(StoreLoginForgotRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $this->resets->sendLink($email, app()->getLocale());

        return redirect()->route('central.password.request')
            ->with('status', __('identity::store_login.link_sent'))
            ->with('email', $email);
    }

    public function edit(Request $request, string $token): View
    {
        $email = (string) $request->query('email', '');

        return view('content::central.auth.reset-password', [
            'seo' => $this->seo->make(__('identity::store_login.reset_title'), __('identity::store_login.reset_description'), noindex: true),
            'action' => route('central.password.update'),
            'token' => $token,
            'email' => $email,
            'invalid' => $email === '' || ! $this->resets->tokenIsValid($email, $token),
        ]);
    }

    public function update(StoreLoginResetRequest $request): RedirectResponse
    {
        $done = $this->resets->reset((string) $request->validated('email'), (string) $request->validated('token'), (string) $request->validated('password'));

        if (! $done) {
            return redirect()->route('central.password.reset', ['token' => (string) $request->validated('token'), 'email' => (string) $request->validated('email')]);
        }

        return redirect()->route('central.login')->with('status', __('identity::store_login.reset_done'));
    }
}
