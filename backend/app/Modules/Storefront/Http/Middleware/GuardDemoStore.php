<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Platform\Support\DemoStore;
use App\Shared\Http\JsonResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only platform demo store (decision #117 a, WEB-004). On the demo store's storefront
 * and Store API every write (cart, wishlist, reviews, newsletter, sign in / up, checkout)
 * answers with the "demo store" notice instead of acting — JSON 403 {message} for AJAX
 * (toasted by resources/js/core/http.js), else a redirect back with the notice flashed for
 * the demo bar. Checkout and account pages redirect home the same way. Browsing, search
 * and the shopper preferences (country / currency / language) keep working. Every
 * response carries `X-Robots-Tag: noindex`. Other stores and the vendor panel are untouched.
 */
class GuardDemoStore
{
    public const NOTICE_FLASH = 'demo_store_notice';

    /** Writes the demo keeps (route names, prefix match). */
    private const ALLOWED_WRITES = ['storefront.preferences', 'storefront.search', 'store-api.v1.search'];

    /** Pages that only make sense with a real order or account. */
    private const BLOCKED_PAGES = ['storefront.checkout', 'storefront.account.', 'store-api.v1.checkout'];

    public function handle(Request $request, Closure $next): Response
    {
        $name = (string) $request->route()?->getName();

        if (! DemoStore::isCurrent() || ! Str::startsWith($name, ['storefront.', 'store-api.'])) {
            return $next($request);
        }

        $blocked = $request->isMethodSafe()
            ? Str::startsWith($name, self::BLOCKED_PAGES) && ! Str::startsWith($name, 'storefront.account.login')
            : ! Str::startsWith($name, self::ALLOWED_WRITES);

        $response = $blocked ? $this->notice($request) : $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function notice(Request $request): Response
    {
        $message = (string) __('content::theme_preview.demo.blocked');

        if ($request->expectsJson() || $request->is('store-api/*')) {
            return JsonResponder::error($message, 403);
        }

        $back = url()->previous();
        $target = $back !== $request->fullUrl() && str_starts_with($back, $request->getSchemeAndHttpHost()) ? $back : url('/');

        return redirect()->to($target)->with(self::NOTICE_FLASH, $message);
    }
}
