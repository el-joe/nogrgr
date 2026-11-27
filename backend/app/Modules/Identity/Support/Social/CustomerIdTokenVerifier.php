<?php

namespace App\Modules\Identity\Support\Social;

use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Exceptions\SocialAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifies an OpenID id_token a shopper's app / browser obtained from Google or Apple
 * (customer social sign-in, P11): Google through its tokeninfo endpoint, Apple by the
 * RS256 signature against Apple's JWKS (cached 12 h, refreshed once on an unknown key).
 * iss, aud (the platform client id + the shopper app ids of Admin › Integrations), exp
 * and the optional nonce are checked. Credentials come from the DB-backed integration
 * settings overlaid on config('services.<provider>.*').
 */
class CustomerIdTokenVerifier
{
    public const GOOGLE_TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    public const APPLE_KEYS_URL = 'https://appleid.apple.com/auth/keys';

    public const APPLE_KEYS_CACHE = 'social:apple:jwks';

    public const GOOGLE_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public const TIMEOUT_SECONDS = 10;

    /**
     * Providers a shopper can sign in with: switched on and with at least one audience.
     *
     * @return list<SocialProvider>
     */
    public static function available(): array
    {
        return array_values(array_filter(SocialProvider::cases(), static fn (SocialProvider $provider): bool => self::isAvailable($provider)));
    }

    public static function isAvailable(SocialProvider $provider): bool
    {
        return filter_var(config('services.'.$provider->value.'.enabled', false), FILTER_VALIDATE_BOOL)
            && self::audiences($provider) !== [];
    }

    /**
     * Accepted `aud` values: the provider client id + the shopper app ids (comma / line separated).
     *
     * @return list<string>
     */
    public static function audiences(SocialProvider $provider): array
    {
        $ids = [(string) config('services.'.$provider->value.'.client_id', '')];
        $extra = preg_split('/[\s,]+/', (string) config('services.'.$provider->value.'.customer_client_ids', '')) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', [...$ids, ...$extra]), static fn (string $id): bool => $id !== '')));
    }

    /**
     * @throws SocialAuthException
     */
    public function verify(SocialProvider $provider, string $idToken, ?string $nonce = null): SocialProfileData
    {
        if (! self::isAvailable($provider)) {
            throw SocialAuthException::failed($provider->value.' sign-in is not configured.');
        }

        $claims = $provider === SocialProvider::Google ? $this->googleClaims($idToken) : $this->appleClaims($idToken);
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $issuerOk = $provider === SocialProvider::Google
            ? in_array($claims['iss'] ?? null, self::GOOGLE_ISSUERS, true)
            : ($claims['iss'] ?? null) === AppleOAuthClient::ISSUER;

        if (! $issuerOk
            || array_intersect(array_filter($audiences, 'is_string'), self::audiences($provider)) === []
            || ! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < time()
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === ''
            || ! $this->nonceMatches($claims['nonce'] ?? null, $nonce)) {
            throw SocialAuthException::failed($provider->value.' id_token rejected.');
        }

        $email = is_string($claims['email'] ?? null) ? mb_strtolower($claims['email']) : '';

        return new SocialProfileData(
            provider: $provider,
            id: $claims['sub'],
            email: $email,
            emailVerified: $email !== '' && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            name: is_string($claims['name'] ?? null) && trim($claims['name']) !== '' ? mb_substr(trim($claims['name']), 0, 120) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function googleClaims(string $idToken): array
    {
        try {
            $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)->get(self::GOOGLE_TOKENINFO_URL, ['id_token' => $idToken]);
        } catch (ConnectionException $exception) {
            throw SocialAuthException::failed('Google unreachable: '.$exception->getMessage());
        }

        $claims = $response->json();

        if ($response->failed() || ! is_array($claims)) {
            throw SocialAuthException::failed('Google tokeninfo refused the token.');
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function appleClaims(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw SocialAuthException::failed('Apple id_token malformed.');
        }

        $header = json_decode((string) self::base64UrlDecode($parts[0]), true);
        $claims = json_decode((string) self::base64UrlDecode($parts[1]), true);
        $signature = self::base64UrlDecode($parts[2]);

        if (! is_array($header) || ! is_array($claims) || $signature === false || ($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw SocialAuthException::failed('Apple id_token malformed.');
        }

        $key = $this->appleKey($header['kid']);

        if ($key === null || openssl_verify($parts[0].'.'.$parts[1], $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw SocialAuthException::failed('Apple id_token signature invalid.');
        }

        return $claims;
    }

    private function appleKey(string $kid): ?string
    {
        $find = static function (array $keys) use ($kid): ?array {
            foreach ($keys as $key) {
                if (is_array($key) && ($key['kid'] ?? null) === $kid && ($key['kty'] ?? null) === 'RSA') {
                    return $key;
                }
            }

            return null;
        };

        $jwk = $find($this->appleKeys(false)) ?? $find($this->appleKeys(true));

        return $jwk !== null && is_string($jwk['n'] ?? null) && is_string($jwk['e'] ?? null) ? self::rsaPem($jwk['n'], $jwk['e']) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appleKeys(bool $refresh): array
    {
        if ($refresh) {
            Cache::forget(self::APPLE_KEYS_CACHE);
        }

        return Cache::remember(self::APPLE_KEYS_CACHE, now()->addHours(12), static function (): array {
            try {
                $response = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS)->get(self::APPLE_KEYS_URL);
            } catch (ConnectionException $exception) {
                throw SocialAuthException::failed('Apple keys unreachable: '.$exception->getMessage());
            }

            $keys = $response->json('keys');

            if ($response->failed() || ! is_array($keys)) {
                throw SocialAuthException::failed('Apple keys unavailable.');
            }

            return array_values($keys);
        });
    }

    /**
     * Apple puts the SHA-256 of the app's raw nonce (or the nonce itself) in the token.
     */
    private function nonceMatches(mixed $claim, ?string $nonce): bool
    {
        if ($nonce === null || $nonce === '') {
            return true;
        }

        return is_string($claim) && (hash_equals($claim, $nonce) || hash_equals($claim, hash('sha256', $nonce)));
    }

    /**
     * PEM public key of an RSA JWK (modulus n, exponent e; base64url).
     */
    public static function rsaPem(string $modulus, string $exponent): ?string
    {
        $n = self::base64UrlDecode($modulus);
        $e = self::base64UrlDecode($exponent);

        if ($n === false || $e === false || $n === '' || $e === '') {
            return null;
        }

        $integer = static fn (string $bytes): string => "\x02".self::derLength(strlen(ord($bytes[0]) > 0x7F ? "\x00".$bytes : $bytes)).(ord($bytes[0]) > 0x7F ? "\x00".$bytes : $bytes);
        $rsaKey = $integer($n).$integer($e);
        $rsaKey = "\x30".self::derLength(strlen($rsaKey)).$rsaKey;
        $bitString = "\x03".self::derLength(strlen($rsaKey) + 1)."\x00".$rsaKey;
        $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $spki = "\x30".self::derLength(strlen($algorithm.$bitString)).$algorithm.$bitString;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private static function base64UrlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
