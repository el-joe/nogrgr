<?php

namespace App\Modules\Identity\Support\Social;

use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\SocialProvider;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted, short-lived hand-offs of the theme social sign-in between a store host and
 * the platform host that owns the provider callback: "start" (store → platform: which store,
 * where to come back, 10 min) and "result" (platform → store: the verified profile or an
 * error, 2 min). Only this application can read or forge them (APP_KEY).
 */
final class CustomerSocialHandoff
{
    public const START_TTL_SECONDS = 600;

    public const RESULT_TTL_SECONDS = 120;

    public static function start(int $tenantId, string $base, ?string $redirect): string
    {
        return self::seal(['kind' => 'start', 'tenant_id' => $tenantId, 'base' => rtrim($base, '/'), 'redirect' => $redirect, 'exp' => time() + self::START_TTL_SECONDS]);
    }

    /**
     * @return array{tenant_id: int, base: string, redirect: string|null}|null
     */
    public static function readStart(mixed $payload): ?array
    {
        $data = self::open($payload, 'start');

        return $data !== null && is_int($data['tenant_id'] ?? null) && is_string($data['base'] ?? null)
            ? ['tenant_id' => $data['tenant_id'], 'base' => $data['base'], 'redirect' => is_string($data['redirect'] ?? null) ? $data['redirect'] : null]
            : null;
    }

    /**
     * @param  array{tenant_id: int, base: string, redirect: string|null}  $context
     */
    public static function result(array $context, ?SocialProfileData $profile, ?string $error = null): string
    {
        return self::seal([
            'kind' => 'result',
            'tenant_id' => $context['tenant_id'],
            'redirect' => $context['redirect'],
            'profile' => $profile !== null ? ['provider' => $profile->provider->value, 'id' => $profile->id, 'email' => $profile->email, 'verified' => $profile->emailVerified, 'name' => $profile->name] : null,
            'error' => $error,
            'exp' => time() + self::RESULT_TTL_SECONDS,
        ]);
    }

    /**
     * @return array{profile: SocialProfileData|null, error: string|null, redirect: string|null}|null null = invalid / expired / other store
     */
    public static function readResult(mixed $token, int $tenantId): ?array
    {
        $data = self::open($token, 'result');

        if ($data === null || ($data['tenant_id'] ?? null) !== $tenantId) {
            return null;
        }

        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : null;
        $provider = $profile !== null ? SocialProvider::tryFrom((string) ($profile['provider'] ?? '')) : null;

        return [
            'profile' => $provider !== null && is_string($profile['id'] ?? null) ? new SocialProfileData(
                provider: $provider,
                id: $profile['id'],
                email: (string) ($profile['email'] ?? ''),
                emailVerified: (bool) ($profile['verified'] ?? false),
                name: is_string($profile['name'] ?? null) ? $profile['name'] : null,
            ) : null,
            'error' => is_string($data['error'] ?? null) ? $data['error'] : null,
            'redirect' => is_string($data['redirect'] ?? null) ? $data['redirect'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function seal(array $data): string
    {
        return Crypt::encryptString((string) json_encode($data));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function open(mixed $value, string $kind): ?array
    {
        if (! is_string($value) || $value === '' || strlen($value) > 8192) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($value), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($data) && ($data['kind'] ?? null) === $kind && is_int($data['exp'] ?? null) && $data['exp'] >= time() ? $data : null;
    }
}
