<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Requests\Mobile\CheckResetCodeRequest;
use App\Modules\Api\Http\Requests\Mobile\ForgotPasswordRequest;
use App\Modules\Api\Http\Requests\Mobile\ResetPasswordRequest;
use App\Modules\Api\Services\MobilePasswordResetService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Mobile forgot / reset password with an e-mailed 6-digit code (APP-022 / APP-023).
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly MobilePasswordResetService $resets) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->resets->send((string) $request->validated('email'));

        return JsonResponder::success(__st('mobile.reset.sent'), ['resend_in' => MobilePasswordResetService::RESEND_SECONDS]);
    }

    public function check(CheckResetCodeRequest $request): JsonResponse
    {
        if (! $this->resets->check((string) $request->validated('email'), (string) $request->validated('code'))) {
            throw ValidationException::withMessages(['code' => __st('mobile.reset.code_invalid')]);
        }

        return JsonResponder::success(__st('mobile.reset.code_valid'));
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->resets->reset((string) $request->validated('email'), (string) $request->validated('code'), (string) $request->validated('password'));

        return JsonResponder::success(__st('mobile.reset.done'));
    }
}
