<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Storefront\Support\CustomThemes\CustomThemeCsp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the custom theme CSP (and nosniff / referrer policy) to a response whose page was
 * rendered by a custom Twig theme (decision #121). Every other response is untouched.
 */
class ApplyCustomThemeSecurityHeaders
{
    public function __construct(private readonly CustomThemeCsp $csp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->csp->used() && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->csp->policy());
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
