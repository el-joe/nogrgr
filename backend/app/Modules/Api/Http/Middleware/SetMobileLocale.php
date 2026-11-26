<?php

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Services\MobileLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API language: `X-Locale` when the store has it switched on, else the first
 * matching `Accept-Language`, else the store's default language. Messages, labels and
 * static texts of the response follow it; `Content-Language` echoes it.
 */
class SetMobileLocale
{
    public function __construct(private readonly MobileLocales $locales) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $enabled = $this->locales->codes();
        $requested = $request->header('X-Locale');
        $locale = is_string($requested) && in_array($requested, $enabled, true)
            ? $requested
            : ($request->headers->has('Accept-Language') ? $request->getPreferredLanguage($enabled) : null) ?? $this->locales->default();

        if (in_array($locale, $enabled, true)) {
            app()->setLocale($locale);
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('Content-Language', app()->getLocale());
        }

        return $response;
    }
}
