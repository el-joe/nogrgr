<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Routing\VendorPanel;
use App\Shared\Routing\VendorPanelMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central-host vendor panel: identifies the tenant from `/tenant/{store}` (tenant slug),
 * then removes the {store} parameter so controllers are identical in both host modes.
 * Unknown slugs are 404.
 */
class InitializeTenancyByStoreSlug
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug = $route?->parameter(VendorPanel::STORE_PARAMETER);

        if ($route === null || ! is_string($slug)) {
            abort(404);
        }

        $tenant = $this->tenants->findBySlug($slug);

        if ($tenant === null) {
            abort(404);
        }

        $this->tenancy->initialize($tenant);

        $route->forgetParameter(VendorPanel::STORE_PARAMETER);
        URL::defaults([VendorPanel::STORE_PARAMETER => $slug]);
        $request->attributes->set(VendorPanel::MODE_ATTRIBUTE, VendorPanelMode::CentralHost);

        return $next($request);
    }
}
