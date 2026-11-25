<?php

namespace App\Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store API language: `X-Locale` header, else the first supported `Accept-Language`
 * (ar / en), else the app default. Every message and label of the response follows it.
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
        $locale = $request->header('X-Locale');
        $locale = is_string($locale) && in_array($locale, self::LOCALES, true) ? $locale : $request->getPreferredLanguage(self::LOCALES);

        if (is_string($locale) && in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
