<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Shared\Http\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * GET {store-domain}/robots.txt — the store host's own robots (the central host serves
 * Content's). Crawlers may index the storefront; panels, the JSON APIs, the customer
 * account and the address-lookup endpoints are off limits. The store sitemap is listed
 * once a `storefront.sitemap` route exists.
 */
class RobotsController extends Controller
{
    /**
     * @var list<string>
     */
    public const DISALLOW = ['/admin', '/api/', '/store-api/', '/account', '/geo/'];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        if (Route::has('storefront.sitemap')) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('storefront.sitemap');
        }

        $lines[] = '';

        return response(implode("\n", $lines))->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
