<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Requests\Mobile\LogoutRequest;
use App\Modules\Api\Http\Requests\Mobile\MobileLoginRequest;
use App\Modules\Api\Http\Requests\Mobile\MobileRegisterRequest;
use App\Modules\Api\Http\Requests\Mobile\MobileSocialLoginRequest;
use App\Modules\Api\Http\Requests\Mobile\RefreshTokenRequest;
use App\Modules\Api\Http\Resources\Mobile\CustomerResource;
use App\Modules\Api\Services\MobileAuthService;
use App\Modules\Identity\DTOs\LoginCredentialsData;
use App\Modules\Identity\DTOs\RegisterCustomerData;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerEmailVerificationService;
use App\Modules\Identity\Services\CustomerSocialAuthService;
use App\Modules\Identity\Support\Social\CustomerIdTokenVerifier;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile customer auth (APP-020 / APP-021): sign-up, sign-in, refresh, sign-out, me.
 * Sign-in / sign-up / refresh answer `{customer, tokens}`.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly MobileAuthService $auth,
        private readonly CustomerEmailVerificationService $verification,
    ) {}

    public function register(MobileRegisterRequest $request): JsonResponse
    {
        $customer = $this->auth->register(RegisterCustomerData::fromRequest($request), $request->deviceId());
        $this->verification->send($customer);

        return JsonResponder::created((string) __('identity::account.registered', ['name' => $customer->name]), $this->session($request, $customer, $request->deviceId()));
    }

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $customer = $this->auth->login(LoginCredentialsData::fromRequest($request), $request->deviceId(), $request);

        return JsonResponder::success((string) __('identity::account.signed_in', ['name' => $customer->name]), $this->session($request, $customer, $request->deviceId()));
    }

    /**
     * Sign in with Google / Apple (id_token from the native SDK); 201 when the account was created.
     */
    public function social(MobileSocialLoginRequest $request, CustomerSocialAuthService $social): JsonResponse
    {
        if (! CustomerIdTokenVerifier::isAvailable($request->provider())) {
            return JsonResponder::error((string) __('identity::auth.social.unavailable'), 404);
        }

        $result = $social->signIn($request->provider(), (string) $request->validated('id_token'), $request->validated('nonce'), $request->validated('name'));
        $customer = $result['customer'];
        $this->auth->completeSocialSignIn($customer, $request->deviceId(), $result['created']);
        $message = (string) __('identity::account.signed_in', ['name' => $customer->name]);

        return $result['created']
            ? JsonResponder::created($message, $this->session($request, $customer, $request->deviceId()))
            : JsonResponder::success($message, $this->session($request, $customer, $request->deviceId()));
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->auth->refresh((string) $request->validated('refresh_token'));

        if ($tokens === null) {
            return JsonResponder::error((string) __('api::mobile.refresh_invalid'), 401);
        }

        return JsonResponder::success('', ['tokens' => $tokens->toResponse()]);
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->auth->logout($this->customer($request), $request->boolean('everywhere'));

        return JsonResponder::success((string) __('identity::account.signed_out'));
    }

    public function me(Request $request): JsonResponse
    {
        return JsonResponder::success('', ['customer' => (new CustomerResource($this->customer($request)))->resolve($request)]);
    }

    /**
     * @return array{customer: array<string, mixed>, tokens: array<string, mixed>}
     */
    private function session(Request $request, Customer $customer, ?string $deviceId): array
    {
        return [
            'customer' => (new CustomerResource($customer->fresh() ?? $customer))->resolve($request),
            'tokens' => $this->auth->issueTokens($customer, $deviceId)->toResponse(),
        ];
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
