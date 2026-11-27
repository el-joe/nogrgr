<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\DTOs\TokenPairData;
use App\Modules\Api\Enums\MobileTokenAbility;
use App\Modules\Identity\DTOs\LoginCredentialsData;
use App\Modules\Identity\DTOs\RegisterCustomerData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Events\CustomerRegistered;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Notifications\Services\CustomerDeviceService;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;

/**
 * Mobile app sign-in with Sanctum tokens (store DB, guard "sanctum"): a short-lived access
 * token (Mobile + Store API) and a long-lived refresh token (only `auth/refresh`), both
 * named after the app install, so refresh / sign-out replace exactly that install's pair.
 * Same rules as the storefront sign-in (SessionAuthService): 5 tries a minute per e-mail
 * + IP, same message for unknown e-mail and wrong password, inactive accounts refused
 * after the password matched, audit log entries.
 */
class MobileAuthService extends BaseService
{
    public const ACCESS_TTL_MINUTES = 60 * 24;

    public const REFRESH_TTL_DAYS = 60;

    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function __construct(
        private readonly CustomerRepositoryContract $customers,
        private readonly CustomerDeviceService $devices,
        private readonly Auditor $auditor,
    ) {}

    public function login(LoginCredentialsData $credentials, ?string $deviceId, Request $request): Customer
    {
        $key = $this->throttleKey($credentials->email, $request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $this->auditFailure($credentials->email, 'throttled');

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
            ])->status(429);
        }

        $customer = $this->customers->findByEmail($credentials->email);

        if (! $customer instanceof Customer || $customer->password === null || ! Hash::check($credentials->password, $customer->password)) {
            if (! $customer instanceof Customer) {
                Hash::check($credentials->password, $this->dummyHash());
            }

            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->auditFailure($credentials->email, 'invalid_credentials');

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($customer->status !== AccountStatus::Active) {
            $this->auditFailure($credentials->email, 'inactive');

            throw ValidationException::withMessages(['email' => __('identity::auth.inactive')]);
        }

        RateLimiter::clear($key);
        $customer->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->auditor->record(AuditAction::Login, $customer);
        $this->attachDevice($customer, $deviceId);

        return $customer;
    }

    public function register(RegisterCustomerData $data, ?string $deviceId): Customer
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

        $customer->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->auditor->record(AuditAction::Registered, $customer);
        $this->attachDevice($customer, $deviceId);

        return $customer;
    }

    /**
     * Finishes a shopper social sign-in (customer already verified and resolved): audit +
     * binds the app install.
     */
    public function completeSocialSignIn(Customer $customer, ?string $deviceId, bool $created): void
    {
        $this->auditor->record($created ? AuditAction::Registered : AuditAction::Login, $customer);
        $this->attachDevice($customer, $deviceId);
    }

    /**
     * A fresh access + refresh pair for the install; the install's previous pair is revoked.
     */
    public function issueTokens(Customer $customer, ?string $deviceId): TokenPairData
    {
        $name = self::tokenName($deviceId);
        $customer->tokens()->where('name', $name)->delete();

        $accessExpires = now()->addMinutes(self::ACCESS_TTL_MINUTES);
        $refreshExpires = now()->addDays(self::REFRESH_TTL_DAYS);
        $access = $customer->createToken($name, [MobileTokenAbility::Access->value], $accessExpires);
        $refresh = $customer->createToken($name, [MobileTokenAbility::Refresh->value], $refreshExpires);

        return new TokenPairData($access->plainTextToken, $accessExpires, $refresh->plainTextToken, $refreshExpires);
    }

    /**
     * Exchange a refresh token (plain text) for a new pair. Null = unknown, expired or not
     * a refresh token, or the account is no longer active.
     */
    public function refresh(string $plainRefreshToken): ?TokenPairData
    {
        $token = PersonalAccessToken::findToken($plainRefreshToken);
        $customer = $token?->tokenable;

        if ($token === null || ! $customer instanceof Customer || ! $token->can(MobileTokenAbility::Refresh->value) || $token->can(MobileTokenAbility::Access->value)) {
            return null;
        }

        if (($token->expires_at !== null && $token->expires_at->isPast()) || $customer->status !== AccountStatus::Active) {
            $token->delete();

            return null;
        }

        $deviceId = str_starts_with($token->name, 'mobile:') ? substr($token->name, 7) : null;

        return $this->issueTokens($customer, $deviceId !== '' ? $deviceId : null);
    }

    /**
     * Sign out this install (its access + refresh pair) or every install.
     */
    public function logout(Customer $customer, bool $everywhere = false): void
    {
        $current = $customer->currentAccessToken();
        $name = $current instanceof PersonalAccessToken && is_string($current->name) && $current->exists ? $current->name : null;

        if ($everywhere || $name === null) {
            $customer->tokens()->delete();
        } else {
            $customer->tokens()->where('name', $name)->delete();
        }

        if ($name !== null && str_starts_with($name, 'mobile:')) {
            $this->devices->detach($customer, substr($name, 7));
        }

        $this->auditor->record(AuditAction::Logout, $customer);
    }

    /**
     * After a password change / reset: every other install signs in again.
     */
    public function revokeOtherTokens(Customer $customer): void
    {
        $current = $customer->currentAccessToken();
        $query = $customer->tokens();

        if ($current instanceof PersonalAccessToken && is_string($current->name) && $current->exists) {
            $query->where('name', '!=', $current->name);
        }

        $query->delete();
    }

    public static function tokenName(?string $deviceId): string
    {
        return 'mobile:'.($deviceId ?? 'app');
    }

    private function attachDevice(Customer $customer, ?string $deviceId): void
    {
        if ($deviceId !== null) {
            $this->devices->attach($customer, $deviceId);
        }
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'customer-api-login:'.(tenant()?->getTenantKey() ?? 'central').':'.sha1(mb_strtolower(trim($email)).'|'.$request->ip());
    }

    private function auditFailure(string $email, string $reason): void
    {
        $this->auditor->record(AuditAction::LoginFailed, null, ['guard' => 'sanctum', 'email' => $email, 'reason' => $reason]);
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('nogrgr-timing-equaliser');
    }
}
