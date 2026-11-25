<?php

use App\Shared\Routing\VendorPanel;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Deny by default: every route of the admin, affiliate and vendor panels (both vendor
| host modes) must carry `auth:<guard>` and a permission middleware. Guest auth routes
| are allow-listed by name suffix and must use `guest:<guard>`; sign-out routes only
| need `auth:<guard>`.
*/

const PANEL_ROUTE_PREFIXES = [
    'admin.' => 'admin',
    'affiliate.' => 'affiliate',
    VendorPanel::TENANT_NAME_PREFIX => 'vendor',
    VendorPanel::CENTRAL_NAME_PREFIX => 'vendor',
];

const PANEL_GUEST_ROUTE_SUFFIXES = [
    'login',
    'login.store',
    'password.request',
    'password.email',
    'password.reset',
    'password.store',
    'register',
    'register.store',
];

const PANEL_AUTH_ONLY_ROUTE_SUFFIXES = ['logout'];

it('protects every panel route with auth and a permission', function (): void {
    $violations = [];
    $checked = 0;

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();

        foreach (PANEL_ROUTE_PREFIXES as $prefix => $guard) {
            if (! Str::startsWith($name, $prefix)) {
                continue;
            }

            $checked++;
            $suffix = Str::after($name, $prefix);
            $middleware = $route->gatherMiddleware();

            if (in_array($suffix, PANEL_GUEST_ROUTE_SUFFIXES, true)) {
                if (! in_array('guest:'.$guard, $middleware, true)) {
                    $violations[] = "{$name}: allow-listed guest route must use guest:{$guard}";
                }

                continue;
            }

            if (! in_array('auth:'.$guard, $middleware, true)) {
                $violations[] = "{$name}: missing auth:{$guard}";
            }

            if (in_array($suffix, PANEL_AUTH_ONLY_ROUTE_SUFFIXES, true)) {
                continue;
            }

            $hasPermission = collect($middleware)->contains(
                fn ($m) => is_string($m) && Str::startsWith($m, ['permission:', 'role:', 'role_or_permission:', 'can:'])
            );

            if (! $hasPermission) {
                $violations[] = "{$name}: missing permission/role/can middleware";
            }
        }
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($violations)->toBe([]);
});

it('mounts every vendor panel route in both host modes', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $route) => (string) $route->getName());

    $tenantHost = $names->filter(fn (string $name) => Str::startsWith($name, VendorPanel::TENANT_NAME_PREFIX))
        ->map(fn (string $name) => Str::after($name, VendorPanel::TENANT_NAME_PREFIX))
        ->reject(fn (string $suffix) => array_key_exists($suffix, VendorPanel::CENTRAL_AUTH_ROUTES))
        ->sort()->values()->all();

    $centralHost = $names->filter(fn (string $name) => Str::startsWith($name, VendorPanel::CENTRAL_NAME_PREFIX))
        ->map(fn (string $name) => Str::after($name, VendorPanel::CENTRAL_NAME_PREFIX))
        ->sort()->values()->all();

    expect($tenantHost)->not->toBeEmpty()->toBe($centralHost);

    foreach (VendorPanel::CENTRAL_AUTH_ROUTES as $central) {
        expect(Route::has($central))->toBeTrue();
    }
});

it('binds central-host vendor routes to the central domain with the store slug and path tenancy', function (): void {
    foreach (Route::getRoutes() as $route) {
        if (! Str::startsWith((string) $route->getName(), VendorPanel::CENTRAL_NAME_PREFIX)) {
            continue;
        }

        expect($route->getDomain())->toBe(CentralDomains::primary())
            ->and($route->uri())->toStartWith('tenant/{store}')
            ->and($route->gatherMiddleware())->toContain('tenant.path', 'vendor.session');
    }
});

it('names every route so it can be checked', function (): void {
    $unnamed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() === null && $route->uri() !== '{path?}')
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->getDomain().'/'.$route->uri())
        ->values()
        ->all();

    expect($unnamed)->toBe([]);
});
