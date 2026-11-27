<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Exceptions\SocialAuthException;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Identity\Support\Social\CustomerIdTokenVerifier;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Shopper "Sign in with Google / Apple" of the current store (app + theme): verifies the
 * provider id_token, then finds the customer by the provider id, else links the account
 * with the same verified e-mail, else creates one (random password, e-mail confirmed by the
 * provider). Inactive accounts are refused. Store database only.
 */
class CustomerSocialAuthService extends BaseService
{
    public function __construct(
        private readonly CustomerIdTokenVerifier $verifier,
        private readonly CustomerRepositoryContract $customers,
    ) {}

    /**
     * @return array{customer: Customer, created: bool}
     */
    public function signIn(SocialProvider $provider, string $idToken, ?string $nonce = null, ?string $name = null): array
    {
        try {
            $profile = $this->verifier->verify($provider, $idToken, $nonce);
        } catch (SocialAuthException $exception) {
            report($exception);

            throw ValidationException::withMessages(['id_token' => (string) __('identity::auth.social.invalid', ['provider' => $provider->brand()])]);
        }

        return $this->resolve($profile, $name);
    }

    /**
     * @return array{customer: Customer, created: bool}
     */
    public function resolve(SocialProfileData $profile, ?string $name = null): array
    {
        $tenantId = (int) tenant()?->getTenantKey();

        if ($tenantId === 0) {
            throw new LogicException('Customer social sign-in requires tenant context.');
        }

        $column = $profile->provider->idColumn();
        $customer = Customer::query()->where($column, $profile->id)->first();
        $created = false;

        if (! $customer instanceof Customer) {
            $existing = $profile->email !== '' ? $this->customers->findByEmail($profile->email) : null;

            if ($existing instanceof Customer) {
                if (! $profile->emailVerified) {
                    throw ValidationException::withMessages(['id_token' => (string) __('identity::auth.social.unverified_email')]);
                }

                if ($existing->status !== AccountStatus::Active) {
                    throw ValidationException::withMessages(['id_token' => (string) __('identity::auth.inactive')]);
                }

                $existing->forceFill([$column => $profile->id, 'email_verified_at' => $existing->email_verified_at ?? now()])->save();
                $customer = $existing;
            } else {
                if ($profile->email === '') {
                    throw ValidationException::withMessages(['id_token' => (string) __('identity::auth.social.no_email')]);
                }

                $customer = $this->create($profile, $name, $tenantId);
                $created = true;
            }
        }

        if ($customer->status !== AccountStatus::Active) {
            throw ValidationException::withMessages(['id_token' => (string) __('identity::auth.inactive')]);
        }

        $customer->forceFill(['last_login_at' => now()])->saveQuietly();

        return ['customer' => $customer, 'created' => $created];
    }

    private function create(SocialProfileData $profile, ?string $name, int $tenantId): Customer
    {
        return $this->transaction(function () use ($profile, $name, $tenantId): Customer {
            $display = $profile->name ?? (is_string($name) && trim($name) !== '' ? mb_substr(trim($name), 0, 120) : Str::before($profile->email, '@'));

            /** @var Customer $customer */
            $customer = $this->customers->create([
                'name' => $display,
                'email' => $profile->email,
                'password' => Hash::make(Str::random(40)),
                'status' => AccountStatus::Active,
                'locale' => app()->getLocale(),
            ]);
            $customer->forceFill([
                $profile->provider->idColumn() => $profile->id,
                'email_verified_at' => $profile->emailVerified ? now() : null,
            ])->save();

            $this->dispatchAfterCommit(new CustomerRegistered($tenantId, $customer->id));

            return $customer;
        });
    }
}
