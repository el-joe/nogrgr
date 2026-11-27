<?php

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Identity\Models\Customer;
use App\Shared\Http\Middleware\SetLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store API language (ar / en):
 *   1. `X-Locale` header (mobile app, Bearer clients, and every storefront JS call
 *      sends the page's <html lang>);
 *   2. stateful storefront calls (session cookie, no X-Locale): the same resolution as
 *      the web storefront - the signed-in customer's saved language (ApplyCustomerLocale),
 *      else the `locale` cookie (SetLocale);
 *   3. the first supported `Accept-Language`, else the app default.
 * Every message and label of the response (and order locale snapshots) follows it.
 */
class SetStoreApiLocale
{
    /**
     * @var list<string>
     */
    public const LOCALES = ['ar', 'en'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $this->resolve($request);

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?string
    {
        $header = $request->header('X-Locale');

        if (self::supported($header)) {
            return $header;
        }

        if (StartStoreApiSession::isStateful($request)) {
            $customer = Auth::guard('customer')->user();

            if ($customer instanceof Customer && self::supported($customer->locale)) {
                return (string) $customer->locale;
            }

            $cookie = $request->cookie(SetLocale::COOKIE);

            if (self::supported($cookie)) {
                return $cookie;
            }
        }

        $preferred = $request->getPreferredLanguage(self::LOCALES);

        return self::supported($preferred) ? $preferred : null;
    }

    private static function supported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::LOCALES, true);
    }
}
