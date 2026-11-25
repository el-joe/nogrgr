<?php

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\DTOs\ResetPasswordData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Services\PasswordResetService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Forgot / reset password of a central panel (admin, affiliate).
 */
abstract class PanelPasswordResetController extends Controller
{
    public function __construct(protected readonly PasswordResetService $passwords) {}

    abstract protected function guard(): Guard;

    public function request(): View
    {
        $surface = $this->guard()->value;

        return view('identity::auth.forgot-password', [
            'surface' => $surface,
            'action' => route($surface.'.password.email'),
            'loginUrl' => route($surface.'.login'),
        ]);
    }

    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwords->sendResetLink($this->guard(), $request->string('email')->toString());

        return back()->with('status', __('identity::auth.reset.link_sent'));
    }

    public function reset(Request $request, string $token): View
    {
        $surface = $this->guard()->value;

        return view('identity::auth.reset-password', [
            'surface' => $surface,
            'action' => route($surface.'.password.store'),
            'token' => $token,
            'email' => is_string($request->query('email')) ? $request->query('email') : null,
            'loginUrl' => route($surface.'.login'),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $this->passwords->reset($this->guard(), ResetPasswordData::fromRequest($request));

        return redirect()->route($this->guard()->value.'.login')->with('status', __('identity::auth.reset.done'));
    }
}
