<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer login is bound to ONE store (session key SESSION_KEY, set at login /
 * registration). On any other store the session is treated as a guest — the login is
 * dropped before the user is resolved — so customer #5 of store A is never looked up
 * as customer #5 of store B (shared session cookie domain, copied cookies).
 */
class EnsureCustomerSessionTenant
{
    public const SESSION_KEY = 'customer.tenant_id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('customer');

        if (! $guard instanceof SessionGuard || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $authenticated = $guard->hasUser() || $session->has($guard->getName());
        $bound = $session->get(self::SESSION_KEY);
        $current = tenant()?->getTenantKey();

        if ($authenticated && ($current === null || (string) $bound !== (string) $current)) {
            $session->forget([$guard->getName(), self::SESSION_KEY]);
            $guard->forgetUser();
        }

        return $next($request);
    }
}
