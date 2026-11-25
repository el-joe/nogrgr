<?php

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Identity\Http\Middleware\EnsureCustomerSessionTenant;
use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateful Store API calls (like Sanctum's EnsureFrontendRequestsAreStateful): a theme
 * page on the store domain calls without a bearer token / X-Tenant header, so it gets the
 * storefront session (signed-in customer, CSRF protection, store binding). The mobile app
 * (bearer token, X-Tenant) and gateway webhooks stay stateless.
 */
class StartStoreApiSession
{
    public const WEBHOOK_ROUTE = 'store-api.v1.payments.webhook';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! self::isStateful($request)) {
            return $next($request);
        }

        return (new Pipeline(app()))->send($request)->through([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ValidateCsrfToken::class,
            EnsureCustomerSessionTenant::class,
        ])->then($next);
    }

    public static function isStateful(Request $request): bool
    {
        return $request->bearerToken() === null
            && ! InitializeStoreApiTenancy::usesHeader($request)
            && ! $request->routeIs(self::WEBHOOK_ROUTE);
    }
}
