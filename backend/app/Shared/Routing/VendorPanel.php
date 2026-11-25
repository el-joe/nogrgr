<?php

namespace App\Shared\Routing;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

/**
 * The vendor panel is served in two host modes from ONE set of route files:
 *
 *   tenant host   {store-domain}/admin/...         names vendor.*          login {store-domain}/login
 *   central host  {central}/tenant/{store}/...     names vendor-central.*  login {central}/tenant/login
 *
 * Code never names either set directly: use vendor_route('dashboard') (Blade/PHP),
 * which resolves the current mode and store. Enforced by
 * tests/Feature/Architecture/VendorRouteHelperTest.php.
 */
final class VendorPanel
{
    public const STORE_PARAMETER = 'store';

    /** Tenant slug: lowercase letters, digits and inner hyphens (DNS label). */
    public const STORE_PATTERN = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

    public const CENTRAL_PREFIX = 'tenant/{store}';

    public const CENTRAL_NAME_PREFIX = 'vendor-central.';

    public const TENANT_NAME_PREFIX = 'vendor.';

    /** @var list<string> */
    public const CENTRAL_MIDDLEWARE = ['tenant.path', 'web', 'vendor.session', 'audit.requests'];

    public const MODE_ATTRIBUTE = 'vendor_panel.mode';

    /** Session key binding a vendor login to one tenant (checked on every panel request). */
    public const SESSION_TENANT = 'vendor_panel.tenant_id';

    public const SESSION_IMPERSONATOR = 'vendor_panel.impersonator_id';

    /**
     * Auth routes that live outside `/tenant/{store}` in central-host mode.
     *
     * @var array<string, string>
     */
    public const CENTRAL_AUTH_ROUTES = [
        'login' => 'central.tenant.login',
        'login.store' => 'central.tenant.login.store',
        'logout' => 'central.tenant.logout',
    ];

    public static function mode(?Request $request = null): VendorPanelMode
    {
        $request ??= request();

        $attribute = $request->attributes->get(self::MODE_ATTRIBUTE);

        if ($attribute instanceof VendorPanelMode) {
            return $attribute;
        }

        $name = (string) $request->route()?->getName();

        return Str::startsWith($name, self::CENTRAL_NAME_PREFIX)
            ? VendorPanelMode::CentralHost
            : VendorPanelMode::TenantHost;
    }

    /**
     * URL of a vendor panel route in the current (or given) mode.
     *
     * @param  string  $name  route name without prefix, e.g. "dashboard" ("vendor.dashboard" is accepted)
     * @param  array<string, mixed>  $parameters
     */
    public static function route(
        string $name,
        array $parameters = [],
        bool $absolute = true,
        ?VendorPanelMode $mode = null,
        ?string $store = null,
    ): string {
        $name = self::normalize($name);
        $mode ??= self::mode();

        if ($mode === VendorPanelMode::TenantHost) {
            return route(self::TENANT_NAME_PREFIX.$name, $parameters, $absolute);
        }

        $store ??= self::currentStore();

        if (isset(self::CENTRAL_AUTH_ROUTES[$name])) {
            if ($name === 'login' && $store !== null) {
                $parameters = [self::STORE_PARAMETER => $store] + $parameters;
            }

            return route(self::CENTRAL_AUTH_ROUTES[$name], $parameters, $absolute);
        }

        if ($store === null) {
            throw new LogicException("Cannot build central-host vendor URL [{$name}] without a store.");
        }

        return route(self::CENTRAL_NAME_PREFIX.$name, [self::STORE_PARAMETER => $store] + $parameters, $absolute);
    }

    /**
     * Central-host URL for a given store (impersonation, central login redirects, emails).
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function centralUrl(string $store, string $name = 'dashboard', array $parameters = []): string
    {
        return self::route($name, $parameters, true, VendorPanelMode::CentralHost, $store);
    }

    public static function isVendorRouteName(?string $name): bool
    {
        return $name !== null && Str::startsWith($name, [self::TENANT_NAME_PREFIX, self::CENTRAL_NAME_PREFIX]);
    }

    public static function currentStore(): ?string
    {
        $slug = tenancy()->initialized ? tenant('slug') : null;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    private static function normalize(string $name): string
    {
        foreach ([self::CENTRAL_NAME_PREFIX, self::TENANT_NAME_PREFIX] as $prefix) {
            if (Str::startsWith($name, $prefix)) {
                return Str::after($name, $prefix);
            }
        }

        return $name;
    }
}
