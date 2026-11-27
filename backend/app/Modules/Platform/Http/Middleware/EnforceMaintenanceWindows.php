<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\MaintenanceService;
use App\Shared\Http\JsonResponder;
use App\Shared\Tenancy\CentralDomains;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Maintenance windows (ADM-113) on the public surfaces: central website, storefronts
 * and the Store API (route alias "platform.maintenance", after tenancy + locale). A
 * blocking window answers 503 with its message (JSON for the API / JSON requests, the
 * central or themed maintenance page otherwise) and Retry-After when it has an end.
 * Panels never run it; allowed IPs and a signed-in admin on the central host pass.
 */
class EnforceMaintenanceWindows
{
    /**
     * Routes that keep working during maintenance: panel sign-in / switching on the
     * central host, payment webhooks and gateway returns (a charge in flight must
     * settle), locale switch and robots.
     *
     * @var list<string>
     */
    public const EXEMPT_ROUTES = [
        'central.login', 'central.password.*', 'central.tenant.*', 'central.social.*', 'central.locale',
        'central.webhooks.*', 'central.register.payment.*', 'central.register.provisioning*', 'central.robots',
        'storefront.checkout.payment.return', 'storefront.robots', 'store-api.v1.payments.webhook',
    ];

    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        // the central website never runs inside a store, whatever tenancy state is left over
        $tenantId = tenancy()->initialized && ! in_array($request->getHost(), CentralDomains::all(), true) ? (int) tenant()?->getTenantKey() : null;

        if ($request->routeIs(...self::EXEMPT_ROUTES) || ($tenantId === null && auth('admin')->check())) {
            return $next($request);
        }

        $window = $this->maintenance->blocking($tenantId, $request->ip());

        if ($window === null) {
            return $next($request);
        }

        $locale = app()->getLocale();
        $message = $window['message'][$locale] ?? $window['message']['en'] ?? (array_values($window['message'])[0] ?? null);
        $message ??= (string) __('platform::maintenance.public.default_message');
        $headers = $window['ends_at'] !== null ? ['Retry-After' => (string) max(1, $window['ends_at'] - now()->getTimestamp())] : [];

        if ($request->is('store-api/*') || $request->expectsJson()) {
            return JsonResponder::error($message, Response::HTTP_SERVICE_UNAVAILABLE, ['maintenance' => true, 'retry_after' => isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null])
                ->withHeaders($headers);
        }

        View::share('maintenanceMessage', $message);

        throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, $message, null, $headers);
    }
}
