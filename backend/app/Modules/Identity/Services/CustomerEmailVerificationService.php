<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Notifications\CustomerEmailCodeNotification;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;

/**
 * Storefront customer e-mail confirmation with a 6-digit code (SF-ECO-025c, Figma
 * Storefront additions 114:107621): the code is kept hashed in the cache for 10 minutes,
 * 5 wrong tries burn it, a new one can be requested every 45 seconds. Changing the e-mail
 * on the profile clears `email_verified_at` (CustomerProfileService), so it asks again.
 */
class CustomerEmailVerificationService extends BaseService
{
    public const CODE_TTL_MINUTES = 10;

    public const RESEND_SECONDS = 45;

    public const MAX_ATTEMPTS = 5;

    public function isVerified(Customer $customer): bool
    {
        return $customer->email_verified_at !== null;
    }

    /**
     * A new code unless one was sent less than RESEND_SECONDS ago. False when verified or
     * still cooling down.
     */
    public function send(Customer $customer, ?string $storeName = null): bool
    {
        if ($this->isVerified($customer) || $this->secondsUntilResend($customer) > 0) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($customer), [
            'hash' => hash('sha256', $customer->email.'|'.$code),
            'attempts' => 0,
            'sent_at' => now()->getTimestamp(),
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        $customer->notify(new CustomerEmailCodeNotification($code, self::CODE_TTL_MINUTES, $storeName ?? (string) (tenant('name') ?? config('app.name'))));

        return true;
    }

    /**
     * Sends the first code when none is pending (the verify page asks for it on open).
     */
    public function ensureSent(Customer $customer): void
    {
        if (! $this->isVerified($customer) && Cache::get($this->key($customer)) === null) {
            $this->send($customer);
        }
    }

    public function secondsUntilResend(Customer $customer): int
    {
        $pending = Cache::get($this->key($customer));

        if (! is_array($pending)) {
            return 0;
        }

        return max(0, ((int) $pending['sent_at'] + self::RESEND_SECONDS) - now()->getTimestamp());
    }

    /**
     * @return 'verified'|'invalid'|'locked'
     */
    public function verify(Customer $customer, string $code): string
    {
        if ($this->isVerified($customer)) {
            return 'verified';
        }

        $pending = Cache::get($this->key($customer));

        if (! is_array($pending)) {
            return 'invalid';
        }

        if ((int) $pending['attempts'] >= self::MAX_ATTEMPTS) {
            return 'locked';
        }

        if (! hash_equals((string) $pending['hash'], hash('sha256', $customer->email.'|'.$code))) {
            $pending['attempts'] = (int) $pending['attempts'] + 1;
            Cache::put($this->key($customer), $pending, now()->addMinutes(self::CODE_TTL_MINUTES));

            return $pending['attempts'] >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }

        $customer->forceFill(['email_verified_at' => now()])->save();
        Cache::forget($this->key($customer));

        return 'verified';
    }

    private function key(Customer $customer): string
    {
        return 'customer-email-code:'.(tenant()?->getTenantKey() ?? 'central').':'.$customer->getKey();
    }
}
