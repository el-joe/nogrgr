<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Concerns\ResolvesMobileCustomer;
use App\Modules\Api\Http\Resources\Mobile\CustomerResource;
use App\Modules\Api\Services\MobileAuthService;
use App\Modules\Identity\DTOs\UpdateCustomerProfileData;
use App\Modules\Identity\Http\Requests\Storefront\DeleteCustomerAccountRequest;
use App\Modules\Identity\Http\Requests\Storefront\UpdateCustomerPasswordRequest;
use App\Modules\Identity\Http\Requests\Storefront\UpdateCustomerProfileRequest;
use App\Modules\Identity\Http\Requests\Storefront\UpdateNotificationPreferencesRequest;
use App\Modules\Identity\Services\CustomerNotificationPreferences;
use App\Modules\Identity\Services\CustomerProfileService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account (APP-024 … APP-027, APP-029): profile, password, notification switches, delete
 * account. Same rules and services as the storefront account.
 */
class ProfileController extends Controller
{
    use ResolvesMobileCustomer;

    public function __construct(
        private readonly CustomerProfileService $profiles,
        private readonly CustomerNotificationPreferences $preferences,
        private readonly MobileAuthService $auth,
    ) {}

    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $customer = $this->profiles->update($this->customer($request), UpdateCustomerProfileData::fromRequest($request));

        return JsonResponder::success((string) __('identity::account.profile.saved'), ['customer' => (new CustomerResource($customer->fresh() ?? $customer))->resolve($request)]);
    }

    public function password(UpdateCustomerPasswordRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $this->profiles->updatePassword($customer, (string) $request->validated('password'));
        $this->auth->revokeOtherTokens($customer);

        return JsonResponder::success((string) __('identity::account.password.changed'));
    }

    public function preferences(Request $request): JsonResponse
    {
        return JsonResponder::success('', ['preferences' => $this->preferences->for($this->customer($request))]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        /** @var array<string, array<string, bool>> $input */
        $input = (array) $request->validated('preferences', []);

        return JsonResponder::success((string) __('api::mobile.preferences_saved'), ['preferences' => $this->preferences->update($this->customer($request), $input)]);
    }

    public function destroy(DeleteCustomerAccountRequest $request): JsonResponse
    {
        $this->profiles->anonymise($this->customer($request));

        return JsonResponder::success((string) __('identity::account.delete.done'));
    }
}
