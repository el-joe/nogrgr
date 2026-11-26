<?php

namespace App\Modules\Integrations\Support;

use App\Modules\Integrations\Enums\ConnectionErrorType;
use App\Modules\Integrations\Exceptions\ConnectionTestFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * OAuth 2 access token of a Google service account (JWT bearer grant, RS256), used by
 * Firebase Cloud Messaging HTTP v1. The service account JSON comes from the DB settings
 * (Admin › Integrations › Firebase, decision #69). Tokens are cached until shortly before
 * they expire, keyed by the service account's key id.
 */
final class GoogleServiceAccountToken
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public const TTL_SECONDS = 3600;

    /**
     * @return array{client_email: string, private_key: string, private_key_id: string, project_id: string|null}
     */
    public static function parse(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! is_string($data['client_email'] ?? null) || ! is_string($data['private_key'] ?? null)) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, 'The service account file is not a Google service account JSON key.');
        }

        return [
            'client_email' => $data['client_email'],
            'private_key' => $data['private_key'],
            'private_key_id' => is_string($data['private_key_id'] ?? null) ? $data['private_key_id'] : sha1($data['client_email']),
            'project_id' => is_string($data['project_id'] ?? null) ? $data['project_id'] : null,
        ];
    }

    /**
     * Signed JWT assertion for the token endpoint.
     */
    public static function assertion(string $json, string $scope = self::FCM_SCOPE, ?int $now = null): string
    {
        $account = self::parse($json);
        $key = openssl_pkey_get_private($account['private_key']);

        if ($key === false) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, 'The service account private key is not valid.');
        }

        $now ??= time();
        $header = self::encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $account['private_key_id']]));
        $payload = self::encode((string) json_encode([
            'iss' => $account['client_email'],
            'scope' => $scope,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ]));

        $signature = '';

        if (! openssl_sign($header.'.'.$payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, 'The service account key cannot sign (RS256 expected).');
        }

        return $header.'.'.$payload.'.'.self::encode($signature);
    }

    /**
     * A cached access token (fresh = skip the cache, used by the connection test).
     */
    public static function accessToken(string $json, bool $fresh = false): string
    {
        $account = self::parse($json);
        $cacheKey = 'google-sa-token:'.sha1($account['client_email'].'|'.$account['private_key_id']);

        if (! $fresh && is_string($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $response = Http::timeout(10)->acceptJson()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => self::assertion($json),
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            $type = in_array($response->status(), [400, 401, 403], true) ? ConnectionErrorType::AuthFailed : ConnectionErrorType::fromStatus($response->status());

            throw new ConnectionTestFailed($type, is_string($response->json('error_description')) ? $response->json('error_description') : 'HTTP '.$response->status());
        }

        $ttl = max(60, (int) ($response->json('expires_in') ?? self::TTL_SECONDS) - 120);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
