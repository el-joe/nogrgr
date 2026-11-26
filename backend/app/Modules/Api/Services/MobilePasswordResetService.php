<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\Notifications\CustomerPasswordResetCodeNotification;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\CustomerRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mobile forgot / reset password with a 6-digit e-mail code (APP-022 / APP-023) instead of
 * the web reset link: the code is kept hashed in the cache for 15 minutes, 5 wrong tries
 * burn it, a new one every 45 seconds. Requesting never reveals whether the e-mail exists.
 * A successful reset revokes every token of the customer.
 */
class MobilePasswordResetService extends BaseService
{
    public const CODE_TTL_MINUTES = 15;

    public const RESEND_SECONDS = 45;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly CustomerRepositoryContract $customers,
        private readonly Auditor $auditor,
    ) {}

    public function send(string $email): void
    {
        $customer = $this->customers->findByEmail($email);

        if (! $customer instanceof Customer || $customer->status !== AccountStatus::Active) {
            return;
        }

        $pending = Cache::get($this->key($email));

        if (is_array($pending) && ((int) $pending['sent_at'] + self::RESEND_SECONDS) > now()->getTimestamp()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($email), ['hash' => $this->hash($email, $code), 'attempts' => 0, 'sent_at' => now()->getTimestamp()], now()->addMinutes(self::CODE_TTL_MINUTES));

        $customer->notify(new CustomerPasswordResetCodeNotification($code, self::CODE_TTL_MINUTES, (string) (tenant('name') ?? config('app.name'))));
        $this->auditor->record(AuditAction::PasswordResetRequested, null, ['guard' => 'sanctum', 'email' => $email]);
    }

    /**
     * Check the code without using it (the app's code step before the new-password step).
     */
    public function check(string $email, string $code): bool
    {
        return $this->matches($email, $code, consume: false);
    }

    /**
     * @throws ValidationException when the code is wrong, expired or locked
     */
    public function reset(string $email, string $code, string $password): Customer
    {
        $customer = $this->customers->findByEmail($email);

        if (! $customer instanceof Customer || ! $this->matches($email, $code, consume: true)) {
            throw ValidationException::withMessages(['code' => __st('mobile.reset.code_invalid')]);
        }

        $this->transaction(function () use ($customer, $password): void {
            $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $customer->tokens()->delete();
        });

        $this->auditor->record(AuditAction::PasswordReset, $customer);

        return $customer;
    }

    private function matches(string $email, string $code, bool $consume): bool
    {
        $pending = Cache::get($this->key($email));

        if (! is_array($pending) || (int) $pending['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals((string) $pending['hash'], $this->hash($email, $code))) {
            $pending['attempts'] = (int) $pending['attempts'] + 1;
            Cache::put($this->key($email), $pending, now()->addMinutes(self::CODE_TTL_MINUTES));

            return false;
        }

        if ($consume) {
            Cache::forget($this->key($email));
        }

        return true;
    }

    private function hash(string $email, string $code): string
    {
        return hash('sha256', mb_strtolower($email).'|'.$code);
    }

    private function key(string $email): string
    {
        return 'customer-reset-code:'.(tenant()?->getTenantKey() ?? 'central').':'.sha1(mb_strtolower($email));
    }
}
