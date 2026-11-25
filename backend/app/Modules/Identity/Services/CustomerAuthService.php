<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\LoginCredentialsData;
use App\Modules\Identity\DTOs\RegisterCustomerData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Http\Middleware\EnsureCustomerSessionTenant;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use LogicException;

/**
 * Storefront customer sign-in, registration and sign-out (guard "customer", store host).
 * Every login binds the session to the current store (EnsureCustomerSessionTenant) and
 * applies the account language.
 */
class CustomerAuthService extends BaseService
{
    public function __construct(
        private readonly SessionAuthService $sessions,
        private readonly CustomerRepositoryContract $customers,
        private readonly Auditor $auditor,
    ) {}

    public function login(LoginCredentialsData $credentials, Request $request): Customer
    {
        $customer = $this->sessions->attempt(Guard::Customer, $credentials, $request);

        if (! $customer instanceof Customer) {
            throw new LogicException('The customer guard must return a Customer.');
        }

        $this->bindSession($customer, $request);

        return $customer;
    }

    public function register(RegisterCustomerData $data, Request $request): Customer
    {
        $tenantId = (int) tenant()?->getTenantKey();

        if ($tenantId === 0) {
            throw new LogicException('Customer registration requires tenant context.');
        }

        $customer = $this->transaction(function () use ($data, $tenantId): Customer {
            /** @var Customer $customer */
            $customer = $this->customers->create([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'phone_country' => $data->phoneCountry,
                'password' => $data->password,
                'status' => AccountStatus::Active,
                'locale' => app()->getLocale(),
            ]);

            $this->dispatchAfterCommit(new CustomerRegistered($tenantId, $customer->id));

            return $customer;
        });

        $this->sessions->login(Guard::Customer, $customer, $request, $data->remember);
        $this->auditor->record(AuditAction::Registered, $customer);
        $this->bindSession($customer, $request);

        return $customer;
    }

    public function logout(Request $request): void
    {
        $this->sessions->logout(Guard::Customer, $request);
        $request->session()->forget(EnsureCustomerSessionTenant::SESSION_KEY);
    }

    private function bindSession(Customer $customer, Request $request): void
    {
        $request->session()->put(EnsureCustomerSessionTenant::SESSION_KEY, tenant()?->getTenantKey());

        if (Locale::isSupported($customer->locale)) {
            app()->setLocale((string) $customer->locale);
            Cookie::queue(Cookie::make('locale', (string) $customer->locale, 60 * 24 * 365, httpOnly: false));
        }
    }
}
