<?php

namespace App\Shared\Http;

use App\Shared\Routing\Surface;
use App\Shared\Routing\VendorPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Resolves the login / home URL for the surface a request belongs to, based on
 * the route name prefix. Vendor panel URLs stay in the current host mode
 * (store host `/login`, central host `/tenant/login?store=…`).
 */
final class GuardRedirector
{
    public static function surface(Request $request): ?Surface
    {
        $name = (string) $request->route()?->getName();

        if ($name === '') {
            return null;
        }

        if (VendorPanel::isVendorRouteName($name)) {
            return Surface::Vendor;
        }

        foreach ([Surface::Admin, Surface::Affiliate, Surface::Owner, Surface::Storefront, Surface::Api, Surface::Central] as $surface) {
            if (Str::startsWith($name, $surface->routeNamePrefix())) {
                return $surface;
            }
        }

        return null;
    }

    public static function loginUrl(Request $request): string
    {
        return match (self::surface($request)) {
            Surface::Vendor, Surface::VendorAuth => VendorPanel::route('login', mode: VendorPanel::mode($request)),
            Surface::Admin => self::named('admin.login'),
            Surface::Affiliate => self::named('affiliate.login'),
            Surface::Owner => self::named('central.tenant.login'),
            Surface::Storefront => self::named('storefront.account.login'),
            default => url('/'),
        };
    }

    public static function homeUrl(Request $request): string
    {
        return match (self::surface($request)) {
            Surface::Vendor, Surface::VendorAuth => VendorPanel::route('dashboard', mode: VendorPanel::mode($request)),
            Surface::Admin => self::named('admin.dashboard'),
            Surface::Affiliate => self::named('affiliate.dashboard'),
            Surface::Owner => self::named('owner.dashboard'),
            Surface::Storefront => self::named('storefront.home'),
            default => url('/'),
        };
    }

    private static function named(string $route): string
    {
        return Route::has($route) ? route($route) : url('/');
    }
}
