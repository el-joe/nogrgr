<?php

namespace App\Modules\Storefront\Support;

use App\Shared\Tenancy\CentralDomains;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Throwable;

/**
 * Themed error pages on store hosts (SF-ECO-036, SF-ALL-001..004): resources/views/errors/*
 * ask for the store view of a status code; central hosts and unknown hosts keep their
 * own pages. Error views may render outside the route middleware (unmatched URL), so the
 * store is identified here by its domain / sub-domain when tenancy is not running yet.
 */
final class StoreErrorPage
{
    /**
     * @var array<int, string>
     */
    private const VIEWS = [
        403 => 'pages.errors.403',
        404 => 'pages.errors.404',
        419 => 'pages.errors.419',
        500 => 'pages.errors.500',
        503 => 'pages.errors.maintenance',
    ];

    /**
     * The resolved theme view for a status code on a store host, else null.
     */
    public static function view(string $code, ?Request $request = null): ?string
    {
        $request ??= request();

        if (! isset(self::VIEWS[(int) $code]) || in_array($request->getHost(), CentralDomains::all(), true) || ! self::storefrontRequest($request) || ! self::storeIdentified($request)) {
            return null;
        }

        try {
            return StorefrontView::resolve(self::VIEWS[(int) $code]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Storefront routes and unmatched URLs; the vendor panel / Store API on the same host keep theirs.
     */
    private static function storefrontRequest(Request $request): bool
    {
        $route = $request->route();
        $name = $route instanceof Route ? $route->getName() : null;

        return $name === null || str_starts_with($name, 'storefront.');
    }

    private static function storeIdentified(Request $request): bool
    {
        if (tenancy()->initialized) {
            return true;
        }

        try {
            app(InitializeTenancyByDomainOrSubdomain::class)->handle($request, static fn (): null => null);
        } catch (Throwable) {
            return false;
        }

        return tenancy()->initialized;
    }
}
