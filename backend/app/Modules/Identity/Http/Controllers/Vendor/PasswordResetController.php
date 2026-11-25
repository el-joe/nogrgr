<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DTOs\ResetPasswordData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Services\PasswordResetService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Store-host forgot / reset password (VEN-002 / VEN-003, Figma 82:99866): request form,
 * "link sent" state, reset form and the invalid / expired link state.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    public function request(Request $request): View
    {
        $sentTo = $request->session()->get('vendor_reset_sent_to');

        return view('identity::vendor.forgot-password', [
            'action' => vendor_route('password.email'),
            'loginUrl' => vendor_route('login'),
            'sentTo' => is_string($sentTo) ? $sentTo : null,
        ]);
    }

    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $this->passwords->sendResetLink(Guard::Vendor, $email);

        return redirect()->to(vendor_route('password.request'))->with('vendor_reset_sent_to', $email);
    }

    public function reset(Request $request, string $token): View
    {
        $email = is_string($request->query('email')) ? $request->query('email') : null;

        return view('identity::vendor.reset-password', [
            'action' => vendor_route('password.store'),
            'token' => $token,
            'email' => $email,
            'invalid' => ! $this->passwords->tokenIsValid(Guard::Vendor, $email, $token),
            'requestUrl' => vendor_route('password.request'),
            'loginUrl' => vendor_route('login'),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        try {
            $this->passwords->reset(Guard::Vendor, ResetPasswordData::fromRequest($request));
        } catch (ValidationException $exception) {
            return back()->withInput($request->only('email'))->with('vendor_reset_invalid', true)->withErrors($exception->errors());
        }

        return redirect()->to(vendor_route('login'))->with('success', __('identity::auth.reset.done'));
    }
}
