<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Integrations\Enums\MailEncryption;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * The current store's SMTP mailer config (VEN-081, decision #91): read from the tenant
 * `store_settings` (password encrypted with the app key), memoised per request / job
 * (scoped binding). Null outside a store or when the store has no complete SMTP setup.
 */
class StoreMailerResolver
{
    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $resolved = [];

    public function __construct(private readonly StoreSettingRepositoryContract $settings) {}

    /**
     * Laravel mailer config ({transport: smtp, host, …, from}) or null.
     *
     * @return array<string, mixed>|null
     */
    public function config(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $key = (string) tenant()?->getTenantKey();

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        try {
            $stored = $this->settings->get(StoreSettingKey::MailSmtp);
        } catch (Throwable) {
            return null; // store not migrated yet
        }

        return $this->resolved[$key] = is_array($stored) ? self::toMailerConfig($stored) : null;
    }

    public function forget(): void
    {
        $this->resolved = [];
    }

    /**
     * @param  array<string, mixed>  $stored  saved setting (password encrypted)
     * @return array<string, mixed>|null
     */
    public static function toMailerConfig(array $stored, ?string $plainPassword = null): ?array
    {
        $host = trim((string) ($stored['host'] ?? ''));
        $from = trim((string) ($stored['from_address'] ?? ''));

        if ($host === '' || $from === '') {
            return null;
        }

        $password = $plainPassword ?? self::decrypt($stored['password'] ?? null);

        return [
            'transport' => 'smtp',
            'scheme' => (MailEncryption::tryFrom((string) ($stored['encryption'] ?? '')) ?? MailEncryption::Tls)->scheme(),
            'host' => $host,
            'port' => (int) ($stored['port'] ?? 587),
            'username' => ($stored['username'] ?? null) ?: null,
            'password' => $password,
            'timeout' => 15,
            'from' => ['address' => $from, 'name' => ($stored['from_name'] ?? null) ?: (string) (tenant()?->getAttribute('name') ?? config('mail.from.name'))],
        ];
    }

    public static function decrypt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
