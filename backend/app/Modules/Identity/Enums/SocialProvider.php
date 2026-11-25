<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * "Sign in with …" providers for store owners. Credentials live in Integrations
 * (Google / Apple tabs) and are overlaid on config('services.<provider>.*').
 */
enum SocialProvider: string
{
    use HasEnumHelpers;

    case Google = 'google';
    case Apple = 'apple';

    /**
     * Brand name (not translated).
     */
    public function brand(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Apple => 'Apple',
        };
    }

    /**
     * owners column holding the provider's stable user id.
     */
    public function idColumn(): string
    {
        return $this->value.'_id';
    }

    /**
     * Enabled in Integrations and fully configured (a provider that is not shows no button).
     */
    public function isEnabled(): bool
    {
        $config = (array) config('services.'.$this->value, []);

        if (! filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOL) || blank($config['client_id'] ?? null)) {
            return false;
        }

        return match ($this) {
            self::Google => filled($config['client_secret'] ?? null),
            self::Apple => filled($config['team_id'] ?? null) && filled($config['key_id'] ?? null) && filled($config['private_key'] ?? null),
        };
    }

    /**
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $provider): bool => $provider->isEnabled()));
    }
}
