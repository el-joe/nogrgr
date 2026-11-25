<?php

namespace App\Shared\Http;

use Illuminate\Http\Request;

/**
 * Same-host redirect targets only (fixes the old storefront open redirect on
 * `?redirect=`): a relative path ("/cart", "/account/orders?page=2") or an absolute
 * http(s) URL whose host and port equal the current request's. Protocol-relative
 * ("//evil.test"), backslash tricks ("/\evil.test"), other schemes ("javascript:")
 * and other hosts are rejected.
 */
final class SafeRedirect
{
    public static function target(mixed $candidate, Request $request): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '' || strlen($candidate) > 2048 || preg_match('/[\x00-\x1F\x7F\\\\]/', $candidate) === 1) {
            return null;
        }

        if (str_starts_with($candidate, '/')) {
            return str_starts_with($candidate, '//') ? null : $request->getSchemeAndHttpHost().$candidate;
        }

        $parts = parse_url($candidate);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        if (strcasecmp($parts['host'], $request->getHost()) !== 0 || (int) $port !== (int) $request->getPort()) {
            return null;
        }

        return $candidate;
    }
}
