<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Identity\Http\Requests\Storefront\VerifyCustomerEmailRequest;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerEmailVerificationService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * E-mail confirmation code of the signed-in customer (same service as SF-ECO-025c).
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly CustomerEmailVerificationService $verification) {}

    public function status(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        return JsonResponder::success('', [
            'verified' => $this->verification->isVerified($customer),
            'resend_in' => $this->verification->secondsUntilResend($customer),
            'expires_in_minutes' => CustomerEmailVerificationService::CODE_TTL_MINUTES,
        ]);
    }

    public function verify(VerifyCustomerEmailRequest $request): JsonResponse
    {
        $result = $this->verification->verify($this->customer($request), $request->code());

        if ($result !== 'verified') {
            throw ValidationException::withMessages(['code' => __st($result === 'locked' ? 'auth.code_locked' : 'auth.code_invalid')]);
        }

        return JsonResponder::success(__st('auth.verified'), ['verified' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        if ($this->verification->isVerified($customer)) {
            return JsonResponder::success(__st('auth.already_verified'), ['verified' => true, 'resend_in' => 0]);
        }

        $sent = $this->verification->send($customer);

        return JsonResponder::success($sent ? __st('auth.code_sent') : '', ['verified' => false, 'resend_in' => $this->verification->secondsUntilResend($customer)]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
