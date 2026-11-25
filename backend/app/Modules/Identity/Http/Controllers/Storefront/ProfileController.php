<?php

namespace App\Modules\Identity\Http\Controllers\Storefront;

use App\Modules\Identity\DTOs\UpdateCustomerProfileData;
use App\Modules\Identity\Http\Requests\Storefront\DeleteCustomerAccountRequest;
use App\Modules\Identity\Http\Requests\Storefront\UpdateCustomerPasswordRequest;
use App\Modules\Identity\Http\Requests\Storefront\UpdateCustomerProfileRequest;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerProfileService;
use App\Shared\Http\Concerns\RespondsWithJsonOrRedirect;
use App\Shared\Http\Controller;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Account › Profile ({store}/account/profile): details, language, password, delete account.
 */
class ProfileController extends Controller
{
    use RespondsWithJsonOrRedirect;

    public function __construct(private readonly CustomerProfileService $profiles) {}

    public function show(Request $request): View
    {
        return view('identity::storefront.account.profile', [
            'customer' => $this->customer($request),
            'locales' => Locale::enabled(),
            'phoneCountry' => strtoupper($this->customer($request)->phone_country ?? Locale::visitorCountry()),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request): JsonResponse|RedirectResponse
    {
        $customer = $this->profiles->update($this->customer($request), UpdateCustomerProfileData::fromRequest($request));

        return $this->respond($request, __('identity::account.profile.saved'), [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'phone_country' => $customer->phone_country,
            'locale' => $customer->locale,
        ]);
    }

    public function password(UpdateCustomerPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $this->profiles->changePassword($this->customer($request), (string) $request->validated('password'), $request);

        return $this->respond($request, __('identity::account.password.changed'));
    }

    public function destroy(DeleteCustomerAccountRequest $request): JsonResponse|RedirectResponse
    {
        $this->profiles->delete($this->customer($request), $request);

        return $this->respond($request, __('identity::account.delete.done'), redirect: route('storefront.home'));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
