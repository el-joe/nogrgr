<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language for every web surface: `?lang=ar` switches (and is remembered
 * in the plain `locale` cookie for a year), otherwise the cookie decides, otherwise
 * config('app.locale'). The topbar / login language switches link to `?lang=`.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if (is_string($requested) && Locale::isSupported($requested)) {
            app()->setLocale($requested);
            Cookie::queue(Cookie::make(self::COOKIE, $requested, 60 * 24 * 365, httpOnly: false));
        } elseif (Locale::isSupported($cookie = $request->cookie(self::COOKIE))) {
            app()->setLocale((string) $cookie);
        }

        return $next($request);
    }
}
