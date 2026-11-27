<?php

namespace App\Shared\Support;

use App\Shared\Tenancy\CentralDomains;

/**
 * Host-independent links for stored in-app notifications (QA-FINAL-008): a panel link is
 * kept as a path (+ query / fragment) and resolved against the host the reader is on, so
 * it survives a changed central domain, APP_URL or port. Only links of our own panels are
 * shortened: any URL on a central domain / the APP_URL host, or a path under a central
 * panel mount (/admin, /my-account, /affiliate, /tenant/{store}) — links to other hosts
 * (a store's storefront, a payment page) stay absolute.
 */
final class RelativeUrl
{
    /** Path prefixes of the panels served by the central host (Surface mounts). */
    private const PANEL_PATHS = ['/admin', '/my-account', '/affiliate', '/tenant/'];

    public static function of(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : $path;

        if (! self::ownHost(strtolower($parts['host'])) && ! self::panelPath($path)) {
            return $url;
        }

        return $path
            .(isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '');
    }

    private static function ownHost(string $host): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return in_array($host, array_map('strtolower', CentralDomains::all()), true)
            || (is_string($appHost) && strtolower($appHost) === $host);
    }

    private static function panelPath(string $path): bool
    {
        foreach (self::PANEL_PATHS as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, rtrim($prefix, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
