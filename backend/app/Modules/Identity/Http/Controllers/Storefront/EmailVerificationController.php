<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\Http\Requests\Storefront\VerifyCustomerEmailRequest;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerEmailVerificationService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\SafeRedirect;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Account › Verify e-mail ({store}/account/verify-email, SF-ECO-025c): 6-digit code page,
 * verify, resend (45 s cool-down). A verified customer goes on to `redirect` / the profile.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly CustomerEmailVerificationService $verification) {}

    public function show(Request $request): View|RedirectResponse
    {
        $customer = $this->customer($request);
        $target = SafeRedirect::target($request->query('redirect'), $request) ?? route('storefront.account.profile');

        if ($this->verification->isVerified($customer)) {
            return redirect()->to($target)->with('status', __st('auth.already_verified'));
        }

        $this->verification->ensureSent($customer);

        return view('identity::storefront.verify-email', [
            'customer' => $customer,
            'redirect' => $target,
            'resendIn' => $this->verification->secondsUntilResend($customer),
            'minutes' => CustomerEmailVerificationService::CODE_TTL_MINUTES,
        ]);
    }

    public function verify(VerifyCustomerEmailRequest $request): JsonResponse|RedirectResponse
    {
        $result = $this->verification->verify($this->customer($request), $request->code());

        if ($result !== 'verified') {
            throw ValidationException::withMessages(['code' => __st($result === 'locked' ? 'auth.code_locked' : 'auth.code_invalid')]);
        }

        $target = SafeRedirect::target($request->input('redirect'), $request) ?? route('storefront.account.profile');

        return $request->expectsJson()
            ? JsonResponder::success(__st('auth.verified'), redirect: $target)
            : redirect()->to($target)->with('status', __st('auth.verified'));
    }

    public function resend(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        if ($this->verification->isVerified($customer)) {
            return JsonResponder::success(__st('auth.already_verified'), ['resend_in' => 0]);
        }

        $sent = $this->verification->send($customer);

        return JsonResponder::success($sent ? __st('auth.code_sent') : '', ['resend_in' => $this->verification->secondsUntilResend($customer)]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }
}
