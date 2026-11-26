<?php

namespace App\Modules\Ai\Support;

use finfo;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads a shopper-supplied image URL for "search by image" ("Or paste an image
 * URL"): https only, public hosts only (every resolved address must be a public IP —
 * no internal network access), no redirects, 10 MB max, image content types only.
 */
final class RemoteImageFetcher
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{binary: string, mime: string}|null
     */
    public function fetch(string $url): ?array
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! is_string($host) || ! self::isPublicHost($host)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->withOptions(['allow_redirects' => false])->accept('image/*')->get($url);
        } catch (Throwable) {
            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $binary = $response->body();

        if (! $response->successful() || ! in_array($mime, self::MIMES, true) || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        return in_array($detected, self::MIMES, true) ? ['binary' => $binary, 'mime' => $detected] : null;
    }

    public static function isPublicHost(string $host): bool
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
