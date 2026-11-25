<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\UpdateCustomerProfileData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Http\Middleware\EnsureCustomerSessionTenant;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Account › Profile: edit details and language, change password, delete account.
 */
class CustomerProfileService extends BaseService
{
    public const ANONYMISED_NAME = 'Deleted customer';

    public function __construct(
        private readonly CustomerRepositoryContract $customers,
        private readonly CustomerAddressService $addresses,
        private readonly Auditor $auditor,
    ) {}

    public function update(Customer $customer, UpdateCustomerProfileData $data): Customer
    {
        $emailChanged = $customer->email !== $data->email;

        $this->customers->update($customer, [
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'phone_country' => $data->phoneCountry,
            'locale' => $data->locale ?? $customer->locale,
        ]);

        if ($emailChanged) {
            $customer->forceFill(['email_verified_at' => null])->save();
        }

        if ($data->locale !== null) {
            $this->applyLocale($data->locale);
        }

        return $customer;
    }

    /**
     * Save a language picked with `?lang=` while signed in.
     */
    public function rememberLocale(Customer $customer, string $locale): void
    {
        if ($customer->locale !== $locale) {
            $customer->forceFill(['locale' => $locale])->saveQuietly();
        }
    }

    /**
     * New password (current password checked by the FormRequest). Remembered devices
     * are signed out (new remember token); this session stays signed in.
     */
    public function changePassword(Customer $customer, string $password, Request $request): void
    {
        $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        $request->session()->regenerate();
        Auth::guard('customer')->login($customer);
        $this->auditor->record(AuditAction::PasswordChanged, $customer);
    }

    /**
     * Delete the account: personal data is anonymised, saved addresses and API tokens
     * are removed and the row is soft deleted (orders keep their customer_id and their
     * own address snapshots). Signs the customer out.
     */
    public function delete(Customer $customer, Request $request): void
    {
        $this->auditor->record(AuditAction::AccountDeleted, $customer);

        $this->transaction(function () use ($customer): void {
            $this->addresses->deleteAllFor($customer);
            $customer->tokens()->delete();

            $customer->forceFill([
                'name' => self::ANONYMISED_NAME,
                'email' => null,
                'phone' => null,
                'phone_country' => null,
                'password' => null,
                'remember_token' => null,
                'locale' => null,
                'country_code' => null,
                'notification_preferences' => null,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'status' => AccountStatus::Suspended,
            ])->save();

            $customer->delete();
        });

        Auth::guard('customer')->logout();
        $request->session()->forget(EnsureCustomerSessionTenant::SESSION_KEY);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
    }

    private function applyLocale(string $locale): void
    {
        if (Locale::isSupported($locale)) {
            app()->setLocale($locale);
            Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365, httpOnly: false));
        }
    }
}
