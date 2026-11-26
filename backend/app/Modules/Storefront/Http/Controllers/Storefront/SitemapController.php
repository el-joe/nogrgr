<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Services\Seo\SitemapService;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * Store sitemap.xml (home, listings, categories, sellable products, store pages) with
 * hreflang alternates; listed in the store robots.txt.
 */
class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
