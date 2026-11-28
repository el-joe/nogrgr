<?php

namespace App\Shared\Routing;

use App\Shared\Http\Controllers\PanelFallbackController;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Foundation\Http\HealthCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Mounts every module route file into its surface group(s) (see Surface::mounts()).
 * The vendor panel is mounted twice: central host `/tenant/{store}` and store host `/admin`.
 *
 * Central surfaces are bound to the primary central domain; any other central
 * domain (www., 127.0.0.1 …) redirects to it so route names stay unique and
 * `route:cache` works. Tenant surfaces are unconstrained and identified by
 * the tenancy middleware (domain, sub-domain, or X-Tenant header for the API).
 */
class SurfaceRouter
{
    public function __construct(private readonly ModuleRouteRegistry $registry) {}

    public function map(): void
    {
        $primary = CentralDomains::primary();

        Route::get('/up', HealthCheck::class)->name('health');

        $centralHost = [];
        $tenantHost = [];

        foreach (Surface::mountOrder() as $surface) {
            foreach ($surface->mounts($primary) as $mount) {
                if ($mount->isCentralHost()) {
                    $centralHost[] = $mount;
                } else {
                    $tenantHost[] = $mount;
                }
            }
        }

        foreach ($centralHost as $mount) {
            $this->mount($mount);
        }

        $this->mapSecondaryCentralDomains($primary);

        foreach ($tenantHost as $mount) {
            $this->mount($mount);
        }
    }

    private function mount(RouteMount $mount): void
    {
        $files = $this->registry->filesFor($mount->surface);

        if ($files === []) {
            return;
        }

        $group = Route::middleware($mount->middleware)->name($mount->namePrefix);

        if ($mount->prefix !== '') {
            $group->prefix($mount->prefix);
        }

        if ($mount->domain !== null) {
            $group->domain($mount->domain);
        }

        if ($mount->wheres !== []) {
            $group->where($mount->wheres);
        }

        $group->group(function () use ($files): void {
            foreach ($files as $file) {
                require $file;
            }
        });

        $this->mountPanelFallback($mount);
    }

    /**
     * Unknown URLs under a panel prefix (`/admin/…`, `/my-account/…`, `/tenant/{store}/…`,
     * store-host `/admin/…`) run through the panel's middleware before answering 404, so
     * the panel error page (ADM-121, VEN-097) sees the session, the signed-in user and the
     * store. Named outside the panel prefixes ("panel-fallback.*"): it is not a panel page.
     */
    private function mountPanelFallback(RouteMount $mount): void
    {
        if (! in_array($mount->surface, [Surface::Admin, Surface::Owner, Surface::Vendor], true) || $mount->prefix === '') {
            return;
        }

        $group = Route::middleware($mount->middleware)->prefix($mount->prefix);

        if ($mount->domain !== null) {
            $group->domain($mount->domain);
        }

        if ($mount->wheres !== []) {
            $group->where($mount->wheres);
        }

        $name = 'panel-fallback.'.$mount->surface->value.($mount->isCentralHost() ? '.central' : '.store');

        $group->group(static function () use ($name): void {
            // Every verb, not only GET like Route::fallback(): an unknown POST would answer 405 otherwise.
            Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], '{fallbackPlaceholder}', PanelFallbackController::class)
                ->where('fallbackPlaceholder', '.*')->fallback()->name($name);
        });
    }

    private function mapSecondaryCentralDomains(string $primary): void
    {
        foreach (CentralDomains::secondary() as $domain) {
            Route::domain($domain)->group(function () use ($primary): void {
                Route::any('{path?}', static function (Request $request) use ($primary): RedirectResponse {
                    $target = $request->getScheme().'://'.$primary;
                    $port = $request->getPort();

                    if (! in_array($port, [80, 443, null], true)) {
                        $target .= ':'.$port;
                    }

                    return redirect()->to($target.$request->getRequestUri(), 301);
                })->where('path', '.*');
            });
        }
    }
}
