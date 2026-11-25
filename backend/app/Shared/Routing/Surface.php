<?php

namespace App\Shared\Routing;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * A web/API surface of the application. Each module may contribute a route
 * file per surface: app/Modules/<Module>/routes/<surface>.php.
 *
 * A surface is mounted once per host mode (see RouteMount). Only the vendor
 * panel has two mounts: the store's own host (`{store}/admin`) and the central
 * host (`{central}/tenant/{store}`), sharing one route file set.
 */
enum Surface: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Admin = 'admin';
    case Affiliate = 'affiliate';
    case Api = 'api';

    /** Store-owner portal: `{central}/my-account/...` (one owner, many stores). */
    case Owner = 'owner';

    /** Vendor panel pages (`/admin/...` on the store host, `/tenant/{store}/...` on central). */
    case Vendor = 'vendor';

    /** Vendor guest/auth pages on the store host (`/login`). Central host uses `/tenant/login`. */
    case VendorAuth = 'vendor-auth';

    case Storefront = 'storefront';

    /**
     * Central surfaces are served on the central domain(s); the others on tenant domains.
     */
    public function isCentral(): bool
    {
        return in_array($this, [self::Central, self::Admin, self::Affiliate, self::Owner], true);
    }

    /**
     * Route name prefix used in code. The vendor panel's central-host mount gets an
     * internal prefix as well (VendorPanel::CENTRAL_NAME_PREFIX); code must go
     * through vendor_route() instead of naming either directly.
     */
    public function routeNamePrefix(): string
    {
        return match ($this) {
            self::Api => 'api.mobile.v1.',
            self::Vendor, self::VendorAuth => 'vendor.',
            default => $this->value.'.',
        };
    }

    /**
     * Auth guard used by this surface (null = public surface).
     */
    public function guard(): ?string
    {
        return match ($this) {
            self::Admin => 'admin',
            self::Affiliate => 'affiliate',
            self::Owner => 'owner',
            self::Vendor, self::VendorAuth => 'vendor',
            self::Storefront => 'customer',
            self::Api => 'sanctum',
            self::Central => null,
        };
    }

    /**
     * Whether every route of this surface must carry auth + permission middleware.
     */
    public function isPanel(): bool
    {
        return in_array($this, [self::Admin, self::Affiliate, self::Owner, self::Vendor, self::VendorAuth], true);
    }

    /**
     * @return list<RouteMount>
     */
    public function mounts(string $centralDomain): array
    {
        return match ($this) {
            self::Central => [new RouteMount($this, '', 'central.', ['web'], $centralDomain)],
            self::Admin => [new RouteMount($this, 'admin', 'admin.', ['web', 'audit.requests'], $centralDomain)],
            self::Affiliate => [new RouteMount($this, 'affiliate', 'affiliate.', ['web', 'audit.requests'], $centralDomain)],
            self::Owner => [new RouteMount($this, 'my-account', 'owner.', ['web', 'audit.requests'], $centralDomain)],
            self::Api => [new RouteMount($this, 'api/mobile/v1', 'api.mobile.v1.', ['tenant.api', 'api'])],
            self::Vendor => [
                new RouteMount(
                    $this,
                    VendorPanel::CENTRAL_PREFIX,
                    VendorPanel::CENTRAL_NAME_PREFIX,
                    VendorPanel::CENTRAL_MIDDLEWARE,
                    $centralDomain,
                    [VendorPanel::STORE_PARAMETER => VendorPanel::STORE_PATTERN],
                ),
                new RouteMount($this, 'admin', 'vendor.', ['tenant.web', 'web', 'vendor.session', 'audit.requests']),
            ],
            self::VendorAuth => [new RouteMount($this, '', 'vendor.', ['tenant.web', 'web', 'vendor.session', 'audit.requests'])],
            self::Storefront => [new RouteMount($this, '', 'storefront.', ['tenant.web', 'web', 'customer.session', 'customer.locale'])],
        };
    }

    /**
     * Mount order matters: domain-bound central mounts first (including the
     * vendor panel's central-host mount), then tenant surfaces; vendor `/login`
     * and `/admin` before the storefront catch-alls.
     *
     * @return list<self>
     */
    public static function mountOrder(): array
    {
        return [self::Central, self::Admin, self::Affiliate, self::Owner, self::Vendor, self::Api, self::VendorAuth, self::Storefront];
    }
}
