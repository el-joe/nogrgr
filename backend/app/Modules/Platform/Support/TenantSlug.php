<?php

namespace App\Modules\Platform\Support;

use App\Shared\Routing\VendorPanel;
use InvalidArgumentException;

/**
 * Tenant slug rules. The slug addresses the vendor panel on the central host
 * (`{central}/tenant/{slug}`) and is the default store sub-domain
 * (`{slug}.{tenancy.base_domain}`), so it must be a valid DNS label and must not
 * collide with central `/tenant/*` routes or reserved sub-domains.
 */
final class TenantSlug
{
    /**
     * Words used by central `/tenant/*` routes (always reserved, on top of
     * config('tenancy.reserved_subdomains')).
     *
     * @var list<string>
     */
    public const ROUTE_RESERVED = ['login', 'logout', 'stores', 'select', 'register', 'password', 'up', 'health'];

    /**
     * @return list<string>
     */
    public static function reserved(): array
    {
        /** @var list<string> $configured */
        $configured = (array) config('tenancy.reserved_subdomains', []);

        return array_values(array_unique([...self::ROUTE_RESERVED, ...array_map('strtolower', $configured)]));
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::reserved(), true);
    }

    public static function isValid(string $slug): bool
    {
        return preg_match('/^'.VendorPanel::STORE_PATTERN.'$/', $slug) === 1 && ! self::isReserved($slug);
    }

    public static function assertValid(string $slug): void
    {
        if (! self::isValid($slug)) {
            throw new InvalidArgumentException("Invalid or reserved tenant slug [{$slug}].");
        }
    }

    /**
     * Default store host for a slug, e.g. "acme" → "acme.nogrgr.loc".
     */
    public static function subdomainHost(string $slug): string
    {
        return strtolower($slug).'.'.config('tenancy.base_domain');
    }
}
