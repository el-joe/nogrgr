<?php

use App\Modules\Api\Http\Middleware\InitializeStoreApiTenancy;
use App\Modules\Api\Http\Middleware\SetStoreApiLocale;
use App\Modules\Api\Http\Middleware\StartStoreApiSession;
use App\Modules\Identity\Http\Middleware\ApplyCustomerLocale;
use App\Modules\Identity\Http\Middleware\EnsureCustomerSessionTenant;
use App\Modules\Identity\Http\Middleware\EnsureVendorSessionTenant;
use App\Modules\Platform\Http\Middleware\AuditRequests;
use App\Modules\Platform\Http\Middleware\EnforceMaintenanceWindows;
use App\Modules\Platform\Http\Middleware\InitializeTenancyByStoreSlug;
use App\Modules\Storefront\Http\Middleware\GuardDemoStore;
use App\Shared\Http\GuardRedirector;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\Middleware\SetLocale;
use App\Shared\Routing\SurfaceRouter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function (): void {
            app(SurfaceRouter::class)->map();
        },
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('tenant.web', [
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomainOrSubdomain::class,
        ]);

        /* Vendor panel on the central host: {central}/tenant/{store}/... */
        $middleware->group('tenant.path', [
            InitializeTenancyByStoreSlug::class,
        ]);

        $middleware->group('tenant.api', [
            InitializeTenancyByRequestData::class,
        ]);

        /* Store JSON API /store-api/v1 (themes on the store domain + mobile app with X-Tenant). */
        $middleware->group('store.api', [
            InitializeStoreApiTenancy::class,
            StartStoreApiSession::class,
            SetStoreApiLocale::class,
            'throttle:store-api',
            GuardDemoStore::class,
        ]);

        /* `theme` (dark/light) and `locale` are written by JS / ?lang= and read on render. */
        $middleware->encryptCookies(except: ['theme', SetLocale::COOKIE]);
        /* GuardDemoStore: the platform demo store (WEB-004) is read-only; a no-op everywhere else. */
        $middleware->web(append: [SetLocale::class, GuardDemoStore::class]);

        $middleware->alias([
            'audit.requests' => AuditRequests::class,
            /* Maintenance windows (ADM-113): central website, storefronts, Store API. */
            'platform.maintenance' => EnforceMaintenanceWindows::class,
            'vendor.session' => EnsureVendorSessionTenant::class,
            'customer.session' => EnsureCustomerSessionTenant::class,
            'customer.locale' => ApplyCustomerLocale::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->prependToPriorityList(StartSession::class, InitializeTenancyByStoreSlug::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureVendorSessionTenant::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureCustomerSessionTenant::class);

        $middleware->redirectGuestsTo(fn (Request $request): string => GuardRedirector::loginUrl($request));
        $middleware->redirectUsersTo(fn (Request $request): string => GuardRedirector::homeUrl($request));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'store-api/*') || $request->expectsJson(),
        );

        /* Mobile API v1 error envelope: {success: false, message} for every non-validation error (docs/plan/mobile-api.md). */
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/mobile/*') || $exception instanceof ValidationException) {
                return null;
            }

            if ($exception instanceof AuthenticationException) {
                return JsonResponder::error((string) __('api::mobile.unauthenticated'), 401);
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $message = $status === 404 && ! tenancy()->initialized
                    ? (string) __('api::store.tenant_not_found')
                    : ($exception->getMessage() !== '' ? $exception->getMessage() : (SymfonyResponse::$statusTexts[$status] ?? 'Error'));

                return JsonResponder::error($message, $status)->withHeaders($exception->getHeaders());
            }

            return null;
        });

        /* 401 JSON carries the login URL of the current surface / host mode for resources/js/core/http.js. */
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->expectsJson() || $request->is('api/*', 'store-api/*')) {
                return null;
            }

            return JsonResponder::error($exception->getMessage(), 401, redirect: GuardRedirector::loginUrl($request));
        });
    })->create();
