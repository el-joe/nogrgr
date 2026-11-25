<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\Services\SitemapService;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * sitemap.xml and robots.txt for the central host (store hosts serve their own).
 */
class SeoController extends Controller
{
    public function __construct(private readonly SitemapService $sitemap) {}

    public function sitemap(): Response
    {
        return response()
            ->view('content::central.sitemap', ['entries' => $this->sitemap->entries()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /affiliate',
            'Disallow: /my-account',
            'Disallow: /tenant',
            'Disallow: /register/',
            'Disallow: /reset-password',
            'Allow: /',
            '',
            'Sitemap: '.route('central.sitemap'),
            '',
        ];

        return response(implode("\n", $lines))->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
