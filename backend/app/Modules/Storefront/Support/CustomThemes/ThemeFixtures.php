<?php

namespace App\Modules\Storefront\Support\CustomThemes;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\DTOs\Catalog\ProductPageData;
use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Services\CustomThemes\ThemeShell;
use App\Modules\Storefront\Services\CustomThemes\ThemeViewModels;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Sample storefront data run through the real presenters (ThemeViewModels / ThemeShell):
 * the upload validator smoke-renders every template with it, the Pest suite renders the
 * starter theme with it, and the documentation's variable reference is generated from it
 * (ThemeVariableReference), so the docs always match what templates receive.
 */
final class ThemeFixtures
{
    /**
     * Full template context (globals + page variables) for a template.
     *
     * @param  array<string, mixed>  $theme  {name, version}
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function context(CustomThemeTemplate $template, array $theme = ['name' => 'Starter', 'version' => '1.0.0'], array $settings = []): array
    {
        $models = app(ThemeViewModels::class);
        $page = $models->page(self::view($template), self::data($template))
            ?? ['template' => $template, 'vars' => []];

        return ThemeViewModels::plain(self::globals($theme, $settings) + $page['vars'] + ['template' => $template->value]);
    }

    /**
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function globals(array $theme = ['name' => 'Starter', 'version' => '1.0.0'], array $settings = []): array
    {
        $menu = app(ThemeViewModels::class)->tree(self::tree());

        return app(ThemeShell::class)->build([
            'name' => 'Nogrgr Demo Store',
            'logo_url' => null,
            'locale' => 'en',
            'dir' => 'ltr',
            'locales' => [['code' => 'en', 'name' => 'English', 'current' => true], ['code' => 'ar', 'name' => 'العربية', 'current' => false]],
            'currency' => 'USD',
            'currencies' => [['code' => 'USD', 'name' => 'US Dollar'], ['code' => 'SAR', 'name' => 'Saudi Riyal']],
            'country' => ['id' => 191, 'iso2' => 'sa', 'name' => 'Saudi Arabia'],
            'countries' => [['id' => 191, 'iso2' => 'sa', 'name' => 'Saudi Arabia'], ['id' => 229, 'iso2' => 'ae', 'name' => 'United Arab Emirates']],
            'about' => 'Everyday essentials, delivered fast.',
            'copyright' => null,
            'menu_categories' => $menu,
            'header_links' => [['title' => 'Offers', 'url' => route('storefront.offers'), 'external' => false]],
            'footer' => [
                'columns' => ['company' => [['title' => 'About us', 'url' => route('storefront.pages.show', 'about')]], 'help' => [['title' => 'Contact us', 'url' => route('storefront.pages.show', 'contact')]]],
                'legal' => [['title' => 'Privacy policy', 'url' => route('storefront.pages.show', 'privacy')]],
            ],
            'social' => [['network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/nogrgr']],
            'customer' => ['signed_in' => false, 'name' => null],
            'cart_count' => 2,
            'year' => 2026,
            'path' => '/',
            'url' => route('storefront.home'),
            'query' => null,
            'theme' => $theme,
            'settings' => $settings,
        ]);
    }

    public static function view(CustomThemeTemplate $template): string
    {
        return $template->views()[0] ?? 'pages.home';
    }

    /**
     * The data our controllers pass to StorefrontView::make for this page.
     *
     * @return array<string, mixed>
     */
    public static function data(CustomThemeTemplate $template): array
    {
        $cards = self::cards();
        $query = new ListingQuery(kind: ListingKind::BestSelling, sort: ListingSort::BestSelling, page: 1, perPage: 20);
        $result = new ListingResult($cards, 42, 1, 20, ['price' => [], 'rating' => [], 'options' => [], 'categories' => [], 'in_stock' => 0]);
        $crumbs = [['name' => 'Home', 'url' => route('storefront.home')]];

        return match ($template) {
            CustomThemeTemplate::Home => [
                'hero' => [new BannerData(1, 'vendor', 'home.hero', 'New season', 'Fresh picks for every day', '/storage/banners/hero.webp', null, route('storefront.new-in'), 'Shop now', 'New season', 1600, 600, 0)],
                'promo' => [],
                'categories' => array_map(static fn (array $node): Category => $node['category'], self::tree()),
                'deals' => array_slice($cards, 0, 2),
                'clearance' => [],
                'flash' => null,
                'best' => $result,
                'rated' => $result,
                'newest' => $result,
            ],
            CustomThemeTemplate::Listing => ['kind' => ListingKind::BestSelling, 'title' => 'Best sellers', 'crumbs' => $crumbs, 'query' => $query, 'result' => $result, 'category' => null, 'categoryOptions' => [['id' => 1, 'name' => 'Fashion']]],
            CustomThemeTemplate::Category => [
                'kind' => ListingKind::Category, 'title' => 'Fashion', 'crumbs' => $crumbs, 'result' => $result,
                'query' => new ListingQuery(kind: ListingKind::Category, sort: ListingSort::Recommended),
                'category' => self::tree()[0]['category'], 'subcategories' => self::tree()[0]['children'], 'categoryOptions' => [],
            ],
            CustomThemeTemplate::Search => [
                'kind' => ListingKind::Search, 'title' => 'Results for “tee”', 'crumbs' => $crumbs, 'result' => $result,
                'query' => new ListingQuery(kind: ListingKind::Search, sort: ListingSort::Relevance, term: 'tee'),
                'category' => null, 'categoryOptions' => [], 'popular' => [],
            ],
            CustomThemeTemplate::Categories => ['title' => 'Categories', 'tree' => self::tree()],
            CustomThemeTemplate::Product => ['title' => 'Cotton tee', 'page' => self::product($cards)],
            CustomThemeTemplate::Cart => ['cart' => self::cart(), 'picks' => $result],
            CustomThemeTemplate::Account => [
                'page' => new LengthAwarePaginator([], 1, 10, 1, ['path' => route('storefront.orders.index')]),
                'rows' => [self::order()],
                'tab' => 'all',
                'tabs' => ['all', 'pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'],
                'search' => null,
            ],
            CustomThemeTemplate::Page => [
                'page' => self::cmsPage(),
                'showSupportForm' => false,
                'menu' => [['title' => 'About us', 'url' => route('storefront.pages.show', 'about'), 'group' => 'company']],
                'currentUrl' => route('storefront.pages.show', 'about'),
            ],
            default => [],
        };
    }

    /**
     * @return list<ProductCardData>
     */
    private static function cards(): array
    {
        return [
            new ProductCardData(101, 'Cotton tee', route('storefront.product', ['product' => 101, 'slug' => 'cotton-tee']), '/storage/products/tee.webp', null, '$19.99', '$24.99', 20, null, 4.6, 18, true, true, false, false, 240, 1999, 6, '250 g', 'Best seller'),
            new ProductCardData(102, 'Linen shirt', route('storefront.product', ['product' => 102, 'slug' => 'linen-shirt']), '/storage/products/shirt.webp', '/storage/products/shirt-2.webp', '$39.00', null, null, null, 4.2, 7, false, true, true, true, 58, 3900),
        ];
    }

    /**
     * @return list<array{category: Category, children: list<Category>}>
     */
    private static function tree(): array
    {
        $category = static function (int $id, string $name, string $slug, ?int $parent = null): Category {
            $model = new Category;
            $model->forceFill(['id' => $id, 'slug' => $slug, 'parent_id' => $parent]);
            $model->setTranslation('name', 'en', $name)->setTranslation('description', 'en', 'Clothes for every day.');

            return $model;
        };

        return [['category' => $category(1, 'Fashion', 'fashion'), 'children' => [$category(2, 'Tees', 'tees', 1)]]];
    }

    /**
     * @param  list<ProductCardData>  $cards
     */
    private static function product(array $cards): ProductPageData
    {
        $store = new StoreProduct;
        $store->forceFill(['id' => 101]);
        $view = new StoreProductView($store, null, null, 'Cotton tee', 'Soft everyday tee.', '<p>100% cotton, <strong>pre-shrunk</strong>.</p>', 'TEE-1', ['/storage/products/tee.webp'], 12, 250, false, [1], [], [], true);
        $tree = self::tree();

        return new ProductPageData(
            view: $view, name: 'Cotton tee', slug: 'cotton-tee', url: $cards[0]->url, description: $view->description, summary: $view->summary,
            images: ['/storage/products/tee.webp'], price: '$19.99', priceMinor: 1999, compare: '$24.99', discount: 20, flash: null, inStock: true, stock: 12,
            attributes: [['id' => 1, 'name' => 'Size', 'swatch' => false, 'options' => [['id' => 11, 'name' => 'M', 'swatch' => null], ['id' => 12, 'name' => 'L', 'swatch' => null]]]],
            variants: [['id' => 501, 'label' => 'M', 'options' => [1 => 11], 'price' => '$19.99', 'compare' => '$24.99', 'discount' => 20, 'price_minor' => 1999, 'compare_minor' => 2499, 'stock' => 5, 'in_stock' => true, 'image' => null]],
            selectedVariantId: null, breadcrumbs: [$tree[0]['category']], category: $tree[0]['category'], bestSelling: true, soldCount: 240,
            delivery: ['label' => 'Delivery in 2–4 days', 'min_date' => '2026-12-01', 'max_date' => '2026-12-03', 'company' => 'Aramex', 'cutoff_minutes' => null, 'location' => null],
            deliveryDays: [], freeShippingGrams: null, reviews: ['average' => 4.6, 'count' => 18, 'distribution' => [5 => 12, 4 => 4, 3 => 2, 2 => 0, 1 => 0]],
            related: [$cards[1]], favorited: false, metaTitle: null, metaDescription: null,
            media: [['type' => 'image', 'url' => '/storage/products/tee.webp', 'poster' => null]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function cart(): array
    {
        $money = static fn (string $text, int $amount): array => ['amount' => $amount, 'currency' => 'USD', 'formatted' => $text, 'display' => null];

        return [
            'items_count' => 2,
            'lines' => [[
                'id' => 9, 'store_product_id' => 101, 'name' => 'Cotton tee', 'variant' => 'M', 'image_url' => '/storage/products/tee.webp',
                'quantity' => 2, 'available' => true, 'unit_price' => $money('$19.99', 1999), 'total' => $money('$39.98', 3998), 'issue' => null, 'message' => null,
            ]],
            'coupon' => null,
            'shipping' => ['available' => true, 'free' => false, 'message' => null],
            'totals' => ['subtotal' => $money('$39.98', 3998), 'flash_discount' => $money('$0.00', 0), 'coupon_discount' => $money('$0.00', 0), 'shipping' => $money('$5.00', 500), 'grand_total' => $money('$44.98', 4498)],
            'can_checkout' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function order(): array
    {
        return [
            'uuid' => '9f1c2d3e-4b5a-4c6d-8e7f-001122334455', 'number' => 'ORD-1001', 'status' => 'shipped', 'status_label' => 'Shipped', 'tone' => 'info',
            'placed' => Carbon::parse('2026-11-20 10:00:00'), 'items_count' => 2, 'trackable' => true,
            'totals' => ['grand_total' => ['formatted' => '$44.98', 'display' => null]],
            'items' => [['name' => 'Cotton tee', 'variant' => 'M', 'image_url' => '/storage/products/tee.webp', 'quantity' => 2, 'total' => ['formatted' => '$39.98', 'display' => null]]],
        ];
    }

    private static function cmsPage(): StorePage
    {
        $page = new StorePage;
        $page->forceFill(['slug' => 'about', 'updated_at' => Carbon::parse('2026-11-01')]);
        $page->setTranslation('title', 'en', 'About us')->setTranslation('body', 'en', '<p>We are a small team that loves good products.</p>');

        return $page;
    }
}
