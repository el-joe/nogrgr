<?php

namespace App\Modules\Storefront\Services\Seo;

use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use App\Modules\Storefront\Services\Pages\StorePageReader;
use App\Shared\Support\Locale;
use Carbon\CarbonInterface;

/**
 * Store sitemap: one <url> per page with xhtml:link hreflang alternates (?lang=). Products =
 * the index of the shopping country (the default / visitor country for crawlers).
 */
class SitemapService
{
    public const MAX_URLS = 45000;

    public function __construct(
        private readonly ProductIndex $index,
        private readonly CategoryCatalog $categories,
    ) {}

    public function xml(): string
    {
        $urls = [];
        $add = static function (string $url, ?CarbonInterface $updated = null, string $priority = '0.5') use (&$urls): void {
            $urls[] = ['loc' => $url, 'lastmod' => $updated?->toAtomString(), 'priority' => $priority];
        };

        $add(route('storefront.home'), null, '1.0');

        foreach (['storefront.best-sellers', 'storefront.new-in', 'storefront.top-rated', 'storefront.offers', 'storefront.categories.index'] as $name) {
            $add(route($name), null, '0.6');
        }

        foreach ($this->categories->visible() as $category) {
            $add(route('storefront.category', ['slug' => $category->slug]), $category->updated_at, '0.7');
        }

        foreach ($this->index->entries() as $entry) {
            $urls[] = ['loc' => ProductCards::url($entry->id, $entry->slug), 'lastmod' => $entry->updatedAt > 0 ? date(DATE_ATOM, $entry->updatedAt) : null, 'priority' => '0.8'];
        }

        if (class_exists(StorePageReader::class)) {
            foreach (app(StorePageReader::class)->sitemapEntries() as $page) {
                $add($page['url'], $page['updated_at'] ?? null, '0.3');
            }
        }

        $locales = array_keys(Locale::enabled());
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach (array_slice($urls, 0, self::MAX_URLS) as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>';

            if ($url['lastmod'] !== null) {
                $xml .= '<lastmod>'.e($url['lastmod']).'</lastmod>';
            }

            $xml .= '<priority>'.$url['priority'].'</priority>';
            $glue = str_contains($url['loc'], '?') ? '&' : '?';

            foreach ($locales as $locale) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.e($locale).'" href="'.e($url['loc'].$glue.'lang='.$locale).'"/>';
            }

            $xml .= "</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
