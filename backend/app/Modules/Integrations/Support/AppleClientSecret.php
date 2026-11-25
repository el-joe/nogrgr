<?php

namespace App\Modules\Integrations\Support;

use App\Modules\Integrations\Enums\ConnectionErrorType;
use App\Modules\Integrations\Exceptions\ConnectionTestFailed;

/**
 * "Sign in with Apple" client secret: an ES256 JWT signed with the team's .p8 key
 * (iss = team id, sub = services id, aud = https://appleid.apple.com). OpenSSL
 * returns a DER signature; JWS needs raw R‖S (64 bytes).
 */
final class AppleClientSecret
{
    public const AUDIENCE = 'https://appleid.apple.com';

    public const TTL_SECONDS = 300;

    public static function make(string $teamId, string $clientId, string $keyId, string $privateKey, ?int $now = null): string
    {
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, 'The private key is not a valid .p8 key.');
        }

        $now ??= time();
        $header = self::encode((string) json_encode(['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT']));
        $payload = self::encode((string) json_encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
            'aud' => self::AUDIENCE,
            'sub' => $clientId,
        ]));

        $signature = '';

        if (! openssl_sign($header.'.'.$payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, 'The private key cannot sign (ES256 / P-256 expected).');
        }

        return $header.'.'.$payload.'.'.self::encode(self::derToRaw($signature));
    }

    /**
     * DER ECDSA signature (SEQUENCE of two INTEGERs) → fixed 64-byte R‖S.
     */
    public static function derToRaw(string $der): string
    {
        $offset = 2;

        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $parts = [];

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $integer = substr($der, $offset + 2, $length);
            $parts[] = str_pad(ltrim($integer, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0].$parts[1];
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
