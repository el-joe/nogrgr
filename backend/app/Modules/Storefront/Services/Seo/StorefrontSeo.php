<?php

namespace App\Modules\Storefront\Services\Seo;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\DTOs\Catalog\ProductPageData;
use App\Modules\Storefront\DTOs\SeoData;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\StorefrontTheme;
use App\Shared\Support\Locale;
use Illuminate\Support\Str;

/**
 * Builds the SeoData of every storefront page: "{page} | {store}" titles, descriptions
 * capped at 160 chars, canonical = this store domain + path (+ page > 1), hreflang for each
 * enabled locale (?lang=), OG / Twitter and JSON-LD (Organization + WebSite on home,
 * Product + Offer + BreadcrumbList on products, BreadcrumbList + ItemList on listings).
 */
class StorefrontSeo
{
    public function __construct(
        private readonly StorefrontContext $context,
        private readonly MoneyPresenter $money,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  array<string, scalar>  $canonicalQuery
     */
    public function page(?string $title, ?string $description = null, array $jsonLd = [], array $canonicalQuery = [], ?string $image = null, string $type = 'website', bool $index = true): SeoData
    {
        $store = StorefrontTheme::storeName();
        $canonical = $this->canonical($canonicalQuery);

        return new SeoData(
            title: $title !== null && $title !== '' ? Str::limit($title, 60, '').' | '.$store : $store,
            description: $description !== null ? $this->clean($description) : null,
            canonical: $canonical,
            alternates: $this->alternates($canonical),
            siteName: $store,
            locale: str_replace('-', '_', Locale::htmlLang()),
            image: $image ?? StorefrontTheme::logoUrl(),
            type: $type,
            index: $index,
            jsonLd: $jsonLd,
        );
    }

    public function home(): SeoData
    {
        $store = StorefrontTheme::storeName();
        $home = route('storefront.home');
        $graph = [
            ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $store, 'url' => $home, 'logo' => StorefrontTheme::logoUrl()],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $store,
                'url' => $home,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('storefront.search').'?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        return $this->page(null, __st('seo.home_description', ['store' => $store]), array_map(static fn (array $node): array => array_filter($node, static fn (mixed $value): bool => $value !== null), $graph));
    }

    /**
     * @param  list<ProductCardData>  $cards
     * @param  list<array{name: string, url: string}>  $crumbs
     * @param  array<string, scalar>  $canonicalQuery
     */
    public function listing(string $title, ?string $description, array $cards, array $crumbs, array $canonicalQuery = [], bool $index = true): SeoData
    {
        $items = [];

        foreach (array_values($cards) as $position => $card) {
            $items[] = ['@type' => 'ListItem', 'position' => $position + 1, 'url' => $card->url, 'name' => $card->name];
        }

        $graph = [$this->breadcrumbs($crumbs)];

        if ($items !== []) {
            $graph[] = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items];
        }

        return $this->page($title, $description, $graph, $canonicalQuery, index: $index);
    }

    public function product(ProductPageData $page): SeoData
    {
        $amount = $this->amount($page->priceMinor);
        $crumbs = [['name' => __st('shell.home'), 'url' => route('storefront.home')]];

        foreach ($page->breadcrumbs as $category) {
            /** @var Category $category */
            $crumbs[] = ['name' => (string) $category->name, 'url' => route('storefront.category', ['slug' => $category->slug])];
        }

        $crumbs[] = ['name' => $page->name, 'url' => $page->url];
        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $page->name,
            'image' => array_slice($page->images, 0, 6),
            'description' => $this->clean($page->summary ?? $page->description ?? $page->name),
            'sku' => $page->view->sku,
            'url' => $page->url,
            'brand' => ['@type' => 'Brand', 'name' => StorefrontTheme::storeName()],
            'offers' => [
                '@type' => 'Offer',
                'url' => $page->url,
                'priceCurrency' => $amount['currency'],
                'price' => $amount['amount'],
                'availability' => $page->inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        if ($page->reviews['count'] > 0) {
            $product['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($page->reviews['average'], 1), 'reviewCount' => $page->reviews['count']];
        }

        return $this->page(
            $page->metaTitle ?: $page->name,
            $page->metaDescription ?: ($page->summary ?? $page->description ?? $page->name),
            [array_filter($product, static fn (mixed $value): bool => $value !== null && $value !== []), $this->breadcrumbs($crumbs)],
            image: $page->images[0] ?? null,
            type: 'product',
        );
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $crumb, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb['name'], 'item' => $crumb['url']], $crumbs, array_keys($crumbs)),
        ];
    }

    /**
     * @return array{amount: float, currency: string}
     */
    private function amount(int $minor): array
    {
        $this->money->useDisplayCurrency($this->context->currency()?->code);
        $presented = $this->money->present($minor);

        return $presented['display'] !== null
            ? ['amount' => round((float) $presented['display']['amount'], 2), 'currency' => (string) $presented['display']['currency']]
            : ['amount' => round($minor / 100, 2), 'currency' => (string) $presented['currency']];
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function canonical(array $query): string
    {
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 1 && $value !== '1');
        ksort($query);

        return url()->current().($query !== [] ? '?'.http_build_query($query) : '');
    }

    /**
     * @return array<string, string>
     */
    private function alternates(string $canonical): array
    {
        $alternates = [];
        $glue = str_contains($canonical, '?') ? '&' : '?';

        foreach (array_keys(Locale::enabled()) as $code) {
            $alternates[$code] = $canonical.$glue.'lang='.$code;
        }

        $alternates['x-default'] = $canonical;

        return $alternates;
    }

    private function clean(string $text): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text))) ?? ''), 160);
    }
}
