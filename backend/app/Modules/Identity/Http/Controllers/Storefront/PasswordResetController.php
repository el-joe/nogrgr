<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\DTOs\ResetPasswordData;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Services\PasswordResetService;
use App\Shared\Http\Concerns\RespondsWithJsonOrRedirect;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Storefront forgot / reset password ({store}/account/forgot-password,
 * /account/reset-password/{token}). Tokens live in the tenant DB.
 */
class PasswordResetController extends Controller
{
    use RespondsWithJsonOrRedirect;

    public function __construct(private readonly PasswordResetService $passwords) {}

    public function request(): View
    {
        return view('identity::storefront.forgot-password');
    }

    public function email(ForgotPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $this->passwords->sendResetLink(Guard::Customer, $request->string('email')->toString());

        return $this->respond($request, __('identity::auth.reset.link_sent'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('identity::storefront.reset-password', [
            'token' => $token,
            'email' => is_string($request->query('email')) ? $request->query('email') : null,
        ]);
    }

    public function store(ResetPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $this->passwords->reset(Guard::Customer, ResetPasswordData::fromRequest($request));

        return $this->respond($request, __('identity::auth.reset.done'), redirect: route('storefront.account.login'));
    }
}
