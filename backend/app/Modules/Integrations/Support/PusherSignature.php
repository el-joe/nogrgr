<?php

namespace App\Modules\Integrations\Support;

/**
 * Pusher HTTP API request signing (also spoken by Laravel Reverb): HMAC-SHA256 of
 * "METHOD\nPATH\nsorted query" with the app secret.
 */
final class PusherSignature
{
    /**
     * @param  array<string, string|int>  $query
     * @return array<string, string|int> query including auth_* parameters
     */
    public static function sign(string $method, string $path, string $key, string $secret, array $query = [], ?int $timestamp = null): array
    {
        $query = [...$query, 'auth_key' => $key, 'auth_timestamp' => $timestamp ?? time(), 'auth_version' => '1.0'];
        ksort($query);

        $parts = [];

        foreach ($query as $name => $value) {
            $parts[] = $name.'='.$value;
        }

        $query['auth_signature'] = hash_hmac('sha256', strtoupper($method)."\n".$path."\n".implode('&', $parts), $secret);

        return $query;
    }
}
