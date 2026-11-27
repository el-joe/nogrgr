<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\DTOs\Catalog\ProductPageData;
use App\Modules\Storefront\DTOs\SeoData;
use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\StorefrontContext;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Throwable;
use Twig\Markup;

/**
 * The shared view-model presenters of custom Twig themes (SF-ALL-006, decision #121):
 * they turn the data our storefront controllers already build for the Blade themes into
 * plain arrays / scalars (plus Markup for HTML we sanitised). Templates never see a PHP
 * object, model or service. Every page gets the shell globals (ThemeShell) + its own
 * variables below; docs/themes/README.md lists them (generated from ThemeFixtures through
 * these same presenters, so the reference cannot drift).
 */
class ThemeViewModels
{
    /** Page builder (VEN-032) home layout reader: build(ResolvedThemeData, Request): array{layout, sections}. */
    private const HOME_SECTIONS = 'App\\Modules\\Storefront\\Services\\Themes\\HomeSectionPayloads';

    /** Page builder store-page sections reader: pageSections(StorePage): list<array>. */
    private const PAGE_SECTIONS = 'App\\Modules\\Storefront\\Services\\Layouts\\StoreLayoutService';

    /**
     * The template and variables for a storefront view, or null when the view always
     * renders with the built-in theme (checkout, payment, sign-in, order detail, …).
     *
     * @param  array<string, mixed>  $data  the data the controller passed to StorefrontView::make
     * @return array{template: CustomThemeTemplate, vars: array<string, mixed>}|null
     */
    public function page(string $view, array $data): ?array
    {
        $template = $this->templateFor($view, $data);

        if ($template === null) {
            return null;
        }

        $vars = match ($template) {
            CustomThemeTemplate::Home => $this->home($data),
            CustomThemeTemplate::Listing, CustomThemeTemplate::Category, CustomThemeTemplate::Search => $this->listing($data),
            CustomThemeTemplate::Categories => $this->categories($data),
            CustomThemeTemplate::Product => $this->product($data),
            CustomThemeTemplate::Cart => $this->cart($data),
            CustomThemeTemplate::Account => $this->account($view, $data),
            CustomThemeTemplate::Page => $this->cmsPage($data),
            CustomThemeTemplate::NotFound => $this->error(404),
            CustomThemeTemplate::ServerError => $this->error(500),
            CustomThemeTemplate::Layout => [],
        };

        $seo = $data['seo'] ?? null;

        return ['template' => $template, 'vars' => $vars + ['seo' => $this->seo($seo instanceof SeoData ? $seo : null, $data['title'] ?? null)]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function templateFor(string $view, array $data = []): ?CustomThemeTemplate
    {
        if ($view === 'pages.listing') {
            $kind = $data['kind'] ?? null;

            return match ($kind) {
                ListingKind::Category => CustomThemeTemplate::Category,
                ListingKind::Search => CustomThemeTemplate::Search,
                default => CustomThemeTemplate::Listing,
            };
        }

        foreach (CustomThemeTemplate::pages() as $template) {
            if (in_array($view, $template->views(), true)) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function home(array $data): array
    {
        $flash = $data['flash'] ?? null;

        return ['home' => [
            'hero' => $this->banners($data['hero'] ?? []),
            'promo' => $this->banners($data['promo'] ?? []),
            'categories' => array_map(fn (mixed $category): array => $category instanceof Category ? $this->category($category) : [], array_values((array) ($data['categories'] ?? []))),
            'flash_deals' => $this->cards($data['deals'] ?? []),
            'clearance' => $this->cards($data['clearance'] ?? []),
            'flash_sale' => $flash instanceof PromotionFlashSale ? [
                'title' => $flash->title,
                'percentage' => $flash->percentage,
                'ends_at' => $this->date($flash->endsAt),
                'banner' => $flash->bannerUrl,
            ] : null,
            'best_sellers' => $this->cards($this->listCards($data['best'] ?? null)),
            'top_rated' => $this->cards($this->listCards($data['rated'] ?? null)),
            'new_in' => $this->cards($this->listCards($data['newest'] ?? null)),
        ] + $this->homeLayout()];
    }

    /**
     * The store's published home layout from the page builder (VEN-032, the signed draft in
     * builder preview): enabled section keys + section payloads as plain arrays. Empty
     * when the builder is not installed or fails (the theme lays out the rails itself).
     *
     * @return array{layout: list<string>, sections: list<array<string, mixed>>}
     */
    private function homeLayout(): array
    {
        $empty = ['layout' => [], 'sections' => []];
        $builder = self::HOME_SECTIONS;

        if (! tenancy()->initialized || ! class_exists($builder)) {
            return $empty;
        }

        try {
            $payload = (array) app($builder)->build(app(StorefrontContext::class)->theme(), request());
        } catch (Throwable) {
            return $empty;
        }

        return [
            'layout' => array_values(array_map('strval', array_filter((array) ($payload['layout'] ?? []), 'is_scalar'))),
            'sections' => array_values(array_filter((array) ($payload['sections'] ?? []), 'is_array')),
        ];
    }

    /**
     * Page-builder sections of a store page (VEN-032) as plain arrays; empty without the builder.
     *
     * @return list<array<string, mixed>>
     */
    private function pageSections(StorePage $page): array
    {
        $layouts = self::PAGE_SECTIONS;

        if (! tenancy()->initialized || ! class_exists($layouts)) {
            return [];
        }

        try {
            return array_values(array_filter((array) app($layouts)->pageSections($page), 'is_array'));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Listing, category and search pages (`pages.listing`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function listing(array $data): array
    {
        $result = $data['result'] ?? null;
        $query = $data['query'] ?? null;
        $kind = $data['kind'] ?? null;
        $category = $data['category'] ?? null;

        $vars = ['listing' => [
            'kind' => $kind instanceof ListingKind ? $kind->value : 'listing',
            'title' => (string) ($data['title'] ?? ''),
            'breadcrumbs' => $this->crumbs($data['crumbs'] ?? []),
            'products' => $result instanceof ListingResult ? $this->cards($result->cards) : [],
            'total' => $result instanceof ListingResult ? $result->total : 0,
            'pagination' => $result instanceof ListingResult && $query instanceof ListingQuery ? $this->pagination($result, $query) : null,
            'sort' => $query instanceof ListingQuery && $kind instanceof ListingKind ? $this->sorts($kind, $query->sort) : ['current' => null, 'options' => []],
            'filter_categories' => array_map(static fn (array $option): array => ['id' => (int) ($option['id'] ?? 0), 'name' => (string) ($option['name'] ?? '')], array_values(array_filter((array) ($data['categoryOptions'] ?? []), 'is_array'))),
        ]];

        if ($kind === ListingKind::Category) {
            $vars['category'] = $category instanceof Category ? $this->category($category) + [
                'description' => $category->description !== null ? (string) $category->description : null,
            ] : null;
            $vars['subcategories'] = array_map(fn (Category $child): array => $this->category($child), array_values(array_filter((array) ($data['subcategories'] ?? []), static fn (mixed $child): bool => $child instanceof Category)));
        }

        if ($kind === ListingKind::Search) {
            $vars['search'] = [
                'term' => $query instanceof ListingQuery ? $query->term : null,
                'popular' => $this->cards($data['popular'] ?? []),
            ];
        }

        return $vars;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function categories(array $data): array
    {
        return ['categories' => $this->tree($data['tree'] ?? [])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function product(array $data): array
    {
        $page = $data['page'] ?? null;

        if (! $page instanceof ProductPageData) {
            return ['product' => null];
        }

        return ['product' => [
            'id' => $page->view->id(),
            'name' => $page->name,
            'slug' => $page->slug,
            'url' => $page->url,
            'summary' => $page->summary,
            'description_html' => $page->description !== null && $page->description !== '' ? new Markup(EmailHtmlSanitizer::clean($page->description), 'UTF-8') : null,
            'images' => array_values($page->images),
            'media' => array_map(static fn (array $slide): array => ['type' => (string) $slide['type'], 'url' => (string) $slide['url'], 'poster' => $slide['poster'] ?? null], $page->media),
            'price' => $page->price,
            'price_minor' => $page->priceMinor,
            'compare_price' => $page->compare,
            'discount_percent' => $page->discount,
            'flash_sale' => $page->flash === null ? null : ['percentage' => (float) $page->flash['percentage'], 'starts_at' => $this->date($page->flash['starts_at']), 'ends_at' => $this->date($page->flash['ends_at'])],
            'in_stock' => $page->inStock,
            'stock' => $page->stock,
            'attributes' => array_map(static fn (array $attribute): array => [
                'id' => (int) $attribute['id'],
                'name' => (string) $attribute['name'],
                'swatch' => (bool) $attribute['swatch'],
                'options' => array_map(static fn (array $option): array => ['id' => (int) $option['id'], 'name' => (string) $option['name'], 'swatch' => $option['swatch']], $attribute['options']),
            ], $page->attributes),
            'variants' => array_map(static fn (array $variant): array => [
                'id' => (int) $variant['id'],
                'label' => (string) $variant['label'],
                'options' => array_map('intval', array_values($variant['options'])),
                'price' => (string) $variant['price'],
                'price_minor' => (int) $variant['price_minor'],
                'compare_price' => $variant['compare'],
                'discount_percent' => $variant['discount'],
                'in_stock' => (bool) $variant['in_stock'],
                'stock' => $variant['stock'],
                'image' => $variant['image'],
            ], $page->variants),
            'selected_variant_id' => $page->selectedVariantId,
            'breadcrumbs' => array_map(fn (Category $category): array => ['name' => (string) $category->name, 'url' => $this->categoryUrl($category)], $page->breadcrumbs),
            'category' => $page->category !== null ? $this->category($page->category) : null,
            'best_selling' => $page->bestSelling,
            'sold_count' => $page->soldCount,
            'delivery' => $page->delivery === null ? null : [
                'label' => (string) $page->delivery['label'],
                'min_date' => (string) $page->delivery['min_date'],
                'max_date' => (string) $page->delivery['max_date'],
                'company' => $page->delivery['company'],
            ],
            'reviews' => [
                'average' => round((float) $page->reviews['average'], 1),
                'count' => (int) $page->reviews['count'],
                'distribution' => array_map('intval', $page->reviews['distribution']),
            ],
            'favorited' => $page->favorited,
            'related' => $this->cards($page->related),
        ]];
    }

    /**
     * The cart (CartResource array, already plain): lines, totals, coupon, shipping. Totals
     * are display strings; `can_checkout` gates the checkout entry button.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cart(array $data): array
    {
        $cart = is_array($data['cart'] ?? null) ? $data['cart'] : [];
        $lines = is_array($cart['lines'] ?? null) ? $cart['lines'] : [];
        $totals = is_array($cart['totals'] ?? null) ? $cart['totals'] : [];

        return ['cart_page' => [
            'items_count' => (int) ($cart['items_count'] ?? 0),
            'lines' => array_map(fn (mixed $line): array => is_array($line) ? $this->cartLine($line) : [], array_values($lines)),
            'totals' => [
                'subtotal' => $this->moneyText($totals['subtotal'] ?? null),
                'flash_discount' => $this->moneyText($totals['flash_discount'] ?? null),
                'coupon_discount' => $this->moneyText($totals['coupon_discount'] ?? null),
                'shipping' => $this->moneyText($totals['shipping'] ?? null),
                'grand_total' => $this->moneyText($totals['grand_total'] ?? null),
            ],
            'coupon' => is_array($cart['coupon'] ?? null) ? [
                'code' => (string) ($cart['coupon']['code'] ?? ''),
                'applied' => (bool) ($cart['coupon']['applied'] ?? false),
                'message' => $cart['coupon']['message'] ?? null,
            ] : null,
            'shipping' => [
                'available' => (bool) ($cart['shipping']['available'] ?? false),
                'free' => (bool) ($cart['shipping']['free'] ?? false),
                'message' => $cart['shipping']['message'] ?? null,
            ],
            'can_checkout' => (bool) ($cart['can_checkout'] ?? false),
            'checkout_url' => route('storefront.checkout'),
            'recommended' => $this->cards($this->listCards($data['picks'] ?? null)),
        ]];
    }

    /**
     * Account overview pages: orders list (`account.section` = orders) and wishlist (wishlist).
     * Order detail, tracking, returns, profile and addresses stay on the built-in theme.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function account(string $view, array $data): array
    {
        $section = $view === 'pages.favorites.index' ? 'wishlist' : 'orders';
        $page = $data['page'] ?? null;

        return ['account' => [
            'section' => $section,
            'search' => $data['search'] ?? null,
            'orders' => $section === 'orders' ? array_map(fn (mixed $row): array => is_array($row) ? $this->orderRow($row) : [], array_values((array) ($data['rows'] ?? []))) : [],
            'order_tab' => $section === 'orders' ? (string) ($data['tab'] ?? 'all') : null,
            'order_tabs' => $section === 'orders' ? array_map(static fn (mixed $tab): array => ['key' => (string) $tab, 'url' => route('storefront.orders.index', $tab === 'all' ? [] : ['status' => $tab])], array_values((array) ($data['tabs'] ?? []))) : [],
            'pagination' => $page instanceof LengthAwarePaginator ? $this->paginator($page) : null,
            'wishlist' => $section === 'wishlist' ? $this->cards($data['cards'] ?? []) : [],
            'wishlist_total' => (int) ($data['total'] ?? 0),
            'links' => array_map(static fn (string $route): string => route($route), [
                'orders' => 'storefront.orders.index',
                'wishlist' => 'storefront.favorites',
                'returns' => 'storefront.returns.index',
                'profile' => 'storefront.account.profile',
                'addresses' => 'storefront.account.addresses',
                'support' => 'storefront.support.index',
            ]),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cmsPage(array $data): array
    {
        $page = $data['page'] ?? null;

        return ['cms_page' => $page instanceof StorePage ? [
            'title' => (string) $page->title,
            'slug' => (string) $page->slug,
            'body_html' => new Markup(EmailHtmlSanitizer::clean((string) $page->body), 'UTF-8'),
            'updated_at' => $this->date($page->updated_at),
            'is_contact' => (bool) ($data['showSupportForm'] ?? false),
            'support_url' => route('storefront.support.index'),
            'menu' => array_map(static fn (array $link): array => ['title' => (string) $link['title'], 'url' => (string) $link['url'], 'current' => ($link['url'] ?? null) === ($data['currentUrl'] ?? null)], array_values(array_filter((array) ($data['menu'] ?? []), 'is_array'))),
            'sections' => $this->pageSections($page),
        ] : null];
    }

    /**
     * @return array<string, mixed>
     */
    public function error(int $code): array
    {
        return ['error' => [
            'code' => $code,
            'title' => __st($code === 404 ? 'errors.404.title' : 'errors.500.title'),
            'message' => __st($code === 404 ? 'errors.404.body' : 'errors.500.body'),
        ]];
    }

    /**
     * One product card (grids, rails, related, wishlist).
     *
     * @return array<string, mixed>
     */
    public function card(ProductCardData $card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->name,
            'url' => $card->url,
            'image' => $card->image,
            'image_hover' => $card->image2,
            'price' => $card->price,
            'price_minor' => $card->priceMinor,
            'compare_price' => $card->compare,
            'discount_percent' => $card->discount,
            'flash_ends_at' => $this->date($card->flash['ends_at'] ?? null),
            'rating' => round($card->rating, 1),
            'review_count' => $card->reviewCount,
            'star_seller' => $card->starSeller,
            'in_stock' => $card->inStock,
            'has_variants' => $card->hasVariants,
            'favorited' => $card->favorited,
            'sold_count' => $card->soldCount,
            'low_stock' => $card->lowStock(),
            'badge' => $card->badge,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, url: string, image: string|null}
     */
    public function category(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'url' => $this->categoryUrl($category),
            'image' => $category->thumbnailUrl(),
        ];
    }

    /**
     * @param  mixed  $tree  CategoryCatalog::tree() nodes
     * @return list<array<string, mixed>>
     */
    public function tree(mixed $tree): array
    {
        $nodes = [];

        foreach ((array) $tree as $node) {
            if (is_array($node) && ($node['category'] ?? null) instanceof Category) {
                $nodes[] = $this->category($node['category']) + [
                    'children' => array_map(fn (Category $child): array => $this->category($child), array_values(array_filter((array) ($node['children'] ?? []), static fn (mixed $child): bool => $child instanceof Category))),
                ];
            }
        }

        return $nodes;
    }

    /**
     * @param  mixed  $cards  list of ProductCardData
     * @return list<array<string, mixed>>
     */
    public function cards(mixed $cards): array
    {
        return array_values(array_map(fn (ProductCardData $card): array => $this->card($card), array_filter(is_iterable($cards) ? [...$cards] : [], static fn (mixed $card): bool => $card instanceof ProductCardData)));
    }

    /**
     * @param  mixed  $banners  list of BannerData
     * @return list<array<string, mixed>>
     */
    public function banners(mixed $banners): array
    {
        $rows = [];

        foreach (is_iterable($banners) ? $banners : [] as $banner) {
            if ($banner instanceof BannerData) {
                $rows[] = [
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'image' => $banner->imageUrl,
                    'mobile_image' => $banner->mobileImageUrl,
                    'url' => $banner->url,
                    'cta_label' => $banner->ctaLabel,
                    'alt' => $banner->alt ?? $banner->title ?? '',
                    'width' => $banner->width,
                    'height' => $banner->height,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{title: string, description: string|null, canonical: string|null}
     */
    public function seo(?SeoData $seo, mixed $title = null): array
    {
        return [
            'title' => $seo->title ?? (is_string($title) ? $title : ''),
            'description' => $seo?->description,
            'canonical' => $seo?->canonical,
        ];
    }

    /**
     * A MoneyPresenter array (or a display string) as the display-currency text.
     */
    public function moneyText(mixed $money): string
    {
        if (is_string($money)) {
            return $money;
        }

        if (! is_array($money)) {
            return '';
        }

        return (string) ($money['display']['formatted'] ?? $money['formatted'] ?? '');
    }

    /**
     * @return array{page: int, last_page: int, total: int, per_page: int, prev_url: string|null, next_url: string|null, pages: list<array{number: int, url: string, current: bool}>}
     */
    public function pagination(ListingResult $result, ListingQuery $query): array
    {
        $last = max(1, (int) ceil($result->total / max(1, $result->perPage)));
        $url = static fn (int $page): string => request()->fullUrlWithQuery(['page' => $page > 1 ? $page : null, 'fragment' => null]);

        return $this->pages($result->page, $last, $result->total, $result->perPage, $url);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array{page: int, last_page: int, total: int, per_page: int, prev_url: string|null, next_url: string|null, pages: list<array{number: int, url: string, current: bool}>}
     */
    public function paginator(LengthAwarePaginator $page): array
    {
        return $this->pages($page->currentPage(), $page->lastPage(), $page->total(), $page->perPage(), static fn (int $number): string => $page->url($number));
    }

    /**
     * @param  callable(int): string  $url
     * @return array{page: int, last_page: int, total: int, per_page: int, prev_url: string|null, next_url: string|null, pages: list<array{number: int, url: string, current: bool}>}
     */
    private function pages(int $current, int $last, int $total, int $perPage, callable $url): array
    {
        $numbers = range(max(1, $current - 2), min($last, $current + 2));

        return [
            'page' => $current,
            'last_page' => $last,
            'total' => $total,
            'per_page' => $perPage,
            'prev_url' => $current > 1 ? $url($current - 1) : null,
            'next_url' => $current < $last ? $url($current + 1) : null,
            'pages' => array_map(static fn (int $number): array => ['number' => $number, 'url' => $url($number), 'current' => $number === $current], $numbers),
        ];
    }

    /**
     * @return array{current: string|null, options: list<array{value: string, label: string, url: string, current: bool}>}
     */
    private function sorts(ListingKind $kind, ListingSort $current): array
    {
        return [
            'current' => $current->value,
            'options' => array_map(static fn (ListingSort $sort): array => [
                'value' => $sort->value,
                'label' => $sort->label(),
                'url' => request()->fullUrlWithQuery(['sort' => $sort->value, 'page' => null, 'fragment' => null]),
                'current' => $sort === $current,
            ], array_values($kind->sorts())),
        ];
    }

    /**
     * @return list<array{name: string, url: string|null}>
     */
    private function crumbs(mixed $crumbs): array
    {
        return array_map(static fn (array $crumb): array => ['name' => (string) ($crumb['name'] ?? ''), 'url' => isset($crumb['url']) ? (string) $crumb['url'] : null], array_values(array_filter((array) $crumbs, 'is_array')));
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function cartLine(array $line): array
    {
        return [
            'id' => $line['id'] ?? null,
            'store_product_id' => $line['store_product_id'] ?? null,
            'name' => (string) ($line['name'] ?? ''),
            'variant' => $line['variant_label'] ?? $line['variant'] ?? null,
            'image' => $line['image_url'] ?? $line['image'] ?? null,
            'url' => $line['url'] ?? null,
            'quantity' => (int) ($line['quantity'] ?? 0),
            'max_quantity' => isset($line['max_quantity']) ? (int) $line['max_quantity'] : null,
            'unit_price' => $this->moneyText($line['unit_price'] ?? $line['price'] ?? null),
            'total' => $this->moneyText($line['total'] ?? $line['line_total'] ?? null),
            'available' => (bool) ($line['available'] ?? true),
            'issue' => isset($line['issue']) && is_string($line['issue']) ? $line['issue'] : ($line['message'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function orderRow(array $row): array
    {
        $uuid = (string) ($row['uuid'] ?? '');

        return [
            'number' => (string) ($row['number'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'status_label' => (string) ($row['status_label'] ?? ''),
            'tone' => (string) ($row['tone'] ?? 'neutral'),
            'placed_at' => $this->date($row['placed'] ?? $row['placed_at'] ?? null),
            'items_count' => (int) ($row['items_count'] ?? 0),
            'total' => $this->moneyText($row['totals']['grand_total'] ?? null),
            'items' => array_map(fn (array $item): array => [
                'name' => (string) ($item['name'] ?? ''),
                'variant' => $item['variant'] ?? null,
                'image' => $item['image_url'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 0),
                'total' => $this->moneyText($item['total'] ?? null),
            ], array_values(array_filter((array) ($row['items'] ?? []), 'is_array'))),
            'url' => $uuid !== '' ? route('storefront.orders.show', $uuid) : null,
            'tracking_url' => $uuid !== '' && ($row['trackable'] ?? false) ? route('storefront.orders.tracking', $uuid) : null,
        ];
    }

    /**
     * @return list<ProductCardData>
     */
    private function listCards(mixed $source): array
    {
        if ($source instanceof ListingResult) {
            return $source->cards;
        }

        return is_array($source) ? array_values(array_filter($source, static fn (mixed $card): bool => $card instanceof ProductCardData)) : [];
    }

    private function categoryUrl(Category $category): string
    {
        return route('storefront.category', ['slug' => (string) $category->slug]);
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Deep-convert a value into what a template may receive: scalars, null, lists /
     * maps and Markup. Enums become their value, dates ISO-8601 strings, Arrayable its
     * array; any other object is dropped (never handed to Twig).
     */
    public static function plain(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value), $value instanceof Markup => $value,
            is_array($value) => array_map(self::plain(...), $value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof Arrayable => array_map(self::plain(...), $value->toArray()),
            default => null,
        };
    }
}
