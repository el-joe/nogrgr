<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Support\StorefrontView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public storefront gate (SF-ALL-001 / 003, old StoreLaunchGate + suspension): a store
 * that is not live yet (pending / provisioning / failed) shows "Opening soon" (200, with
 * the newsletter "Notify me" form), a suspended or cancelled store shows "temporarily
 * unavailable" (503). Only `storefront.*` routes are gated; the vendor panel, Store API
 * and the newsletter sign-up stay reachable. Pushed onto the `web` group by the
 * Storefront module, so it runs after tenancy was initialised by `tenant.web`.
 */
class EnsureStoreAvailable
{
    /**
     * @var list<string>
     */
    private const ALWAYS_OPEN = ['storefront.newsletter.store', 'storefront.robots', 'storefront.preferences'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if (! $tenant instanceof Tenant || ! $request->routeIs('storefront.*') || $request->routeIs(...self::ALWAYS_OPEN)) {
            return $next($request);
        }

        return match ($tenant->status) {
            TenantStatus::Suspended, TenantStatus::Cancelled => response()->view(StorefrontView::resolve('pages.errors.suspended'), [], 503),
            TenantStatus::Pending, TenantStatus::Provisioning, TenantStatus::Failed => response()->view(StorefrontView::resolve('pages.errors.not-launched')),
            default => $next($request),
        };
    }
}
