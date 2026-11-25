<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Services\VendorImpersonationService;
use App\Shared\Routing\VendorPanel;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A vendor login is bound to ONE tenant (session key vendor_panel.tenant_id, set at login).
 * Any vendor-panel request for another tenant — `/tenant/{other}` on the central host or
 * another store's domain — is answered with 404 before the user is resolved, so a staff
 * id from store A is never looked up in store B's database. An admin impersonation
 * whose time ran out (VendorImpersonationService::SESSION_MINUTES) is ended here.
 */
class EnsureVendorSessionTenant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('vendor');
        $tenantKey = tenant()?->getTenantKey();

        $authenticated = $guard instanceof SessionGuard
            && ($guard->hasUser() || $request->session()->has($guard->getName()));

        if ($authenticated) {
            $boundTenant = $request->session()->get(VendorPanel::SESSION_TENANT);

            if ($tenantKey === null || (string) $boundTenant !== (string) $tenantKey) {
                abort(404);
            }

            $impersonation = app(VendorImpersonationService::class);

            if ($impersonation->isExpired($request)) {
                return redirect()->to($impersonation->leave($request, expired: true))
                    ->with('warning', __('identity::vendor_auth.impersonation.expired_session'));
            }
        }

        return $next($request);
    }
}
