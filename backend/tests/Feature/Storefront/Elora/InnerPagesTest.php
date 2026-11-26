<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Engagement\Models\CustomerFavorite;
use App\Modules\Engagement\Models\ProductReview;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Services\StoreThemeService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Commerce/FulfilmentHelpers.php';

/*
| Elora family inner pages (SF-ELO-014 … 036): a store on the "elora-1" theme renders the
| Elora views (themes/elora/pages/*) for listings, categories, product, cart, checkout,
| payment, orders (details / tracking / success), returns, favorites, CMS pages and the
| themed 404 — with the shared data hooks the storefront scripts rely on.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->seed(ThemeCatalogSeeder::class);
    $this->shop = shopSetup();
    $this->shop->tenant->run(fn () => app(StoreThemeService::class)->activate('elora-1'));
    $category = Category::factory()->create(['slug' => 'elora-gadgets', 'name' => 'Gadgets']);
    $this->shop->central->categories()->attach($category->id);
    $this->shop->tenant->run(fn () => $this->shop->own->forceFill(['name' => 'Desk Lamp', 'category_ids' => [$category->id]])->save());
    tenancy()->end();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->sa = ['storefront.country_id' => $this->shop->sa->id];
});

it('renders the Elora listings with filters, sort sheet and the floating filter bar', function (string $path, string $view): void {
    $this->withSession($this->sa)->get(($this->url)($path))
        ->assertOk()
        ->assertViewIs($view)
        ->assertSee('data-filter-form', false)
        ->assertSee('data-elo-autosubmit', false)
        ->assertSee('id="elo-filters"', false)
        ->assertSee('id="elo-sort"', false)
        ->assertSee('elo-fab', false);
})->with([
    'new in' => ['/new-in', 'storefront::themes.elora.pages.listing'],
    'best selling' => ['/best-selling?period=7', 'storefront::themes.elora.pages.listing'],
    'top rated' => ['/top-rated', 'storefront::themes.elora.pages.listing'],
    'offers' => ['/offers', 'storefront::themes.elora.pages.listing'],
    'search' => ['/search?q=lamp', 'storefront::themes.elora.pages.listing'],
    'category' => ['/category/elora-gadgets', 'storefront::themes.elora.pages.listing'],
]);

it('shows the products in the Elora grid and the empty state', function (): void {
    $this->withSession($this->sa)->get(($this->url)('/new-in'))
        ->assertOk()
        ->assertSee('id="elo-listing-grid"', false)
        ->assertSee('Desk Lamp');

    $this->withSession($this->sa)->get(($this->url)('/search?q=zzzz-nothing'))
        ->assertOk()
        ->assertSee('elo-empty', false)
        ->assertSee('No results for');
});

it('renders the Elora categories browser', function (): void {
    $this->withSession($this->sa)->get(($this->url)('/categories'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.categories')
        ->assertSee('Gadgets');
});

it('renders the Elora product page with delivery, privacy and reviews popups', function (): void {
    $response = $this->withSession($this->sa)->get(($this->url)('/products/'.$this->shop->own->id));
    $response = $response->isRedirect() ? $this->withSession($this->sa)->get($response->headers->get('Location')) : $response;

    $response->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.product')
        ->assertSee('data-product-page', false)
        ->assertSee('data-add-to-cart', false)
        ->assertSee('data-gallery', false)
        ->assertSee('id="elo-privacy"', false)
        ->assertSee('data-elo-qty-mirror', false)
        ->assertSee('Desk Lamp');
});

it('shows the reviews section and the reviews modal on a reviewed product', function (): void {
    shopGateway($this->shop->tenant);
    $customer = shopCustomer($this->shop);
    $order = paidShopOrder($this->shop, $customer, [$this->shop->own->id => 1]);
    $this->shop->tenant->run(function () use ($order, $customer): void {
        $line = $order->items()->firstOrFail();
        ProductReview::factory()->create(['store_product_id' => $this->shop->own->id, 'customer_id' => $customer->id, 'order_id' => $order->id, 'order_item_id' => $line->id, 'rating' => 4]);
    });
    tenancy()->end();

    $response = $this->withSession($this->sa)->get(($this->url)('/products/'.$this->shop->own->id));
    $response = $response->isRedirect() ? $this->withSession($this->sa)->get($response->headers->get('Location')) : $response;

    $response->assertOk()
        ->assertSee('data-reviews-preview', false)
        ->assertSee('id="sf-reviews"', false)
        ->assertSee('id="sf-review-write"', false)
        ->assertSee(__st('elora_pages.product.verified_note'));
});

it('renders the Elora cart, empty and filled, with steppers and the summary', function (): void {
    $this->withSession($this->sa)->get(($this->url)('/cart'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.cart')
        ->assertSee(__st('elora_pages.cart.empty_title'));

    $this->postJson(($this->url)('/store-api/v1/cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2])->assertCreated();

    $this->get(($this->url)('/cart'))
        ->assertOk()
        ->assertSee('data-cart-line=', false)
        ->assertSee('data-elo-step="1"', false)
        ->assertSee('data-cart-qty', false)
        ->assertSee('data-coupon-apply', false)
        ->assertSee('$63.80 to checkout');
});

it('renders the Elora checkout and the pay page of a placed order', function (): void {
    shopGateway($this->shop->tenant);
    $customer = shopCustomer($this->shop);
    tenancy()->end();
    $this->actingAsCustomer($customer, $this->shop->tenant);
    $this->postJson(($this->url)('/store-api/v1/cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2])->assertCreated();

    $this->get(($this->url)('/checkout'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.checkout')
        ->assertSee('data-checkout-address', false)
        ->assertSee('data-checkout-summary', false)
        ->assertSee(__st('elora_pages.checkout.total_due'))
        ->assertSee('Pay $63.80');
});

it('renders the Elora order pages for their owner', function (string $path, string $view, string $hook): void {
    shopGateway($this->shop->tenant);
    $customer = shopCustomer($this->shop);
    $order = paidShopOrder($this->shop, $customer, [$this->shop->catalog->id => 2]);
    tenancy()->end();

    $this->actingAsCustomer($customer, $this->shop->tenant)
        ->get(($this->url)(str_replace('{uuid}', $order->uuid, $path)))
        ->assertOk()
        ->assertViewIs($view)
        ->assertSee($hook, false)
        ->assertSee($order->number);
})->with([
    'orders' => ['/account/orders', 'storefront::themes.elora.pages.orders.index', 'data-order-card'],
    'details' => ['/orders/{uuid}', 'storefront::themes.elora.pages.orders.show', 'data-order-actions'],
    'tracking' => ['/orders/{uuid}/tracking', 'storefront::themes.elora.pages.orders.show', 'elo-track-step'],
    'success' => ['/orders/{uuid}?placed=1', 'storefront::themes.elora.pages.orders.success', 'data-order-success'],
]);

it('shows the delivered tracking without upcoming steps', function (): void {
    shopGateway($this->shop->tenant);
    $customer = shopCustomer($this->shop);
    $order = paidShopOrder($this->shop, $customer, [$this->shop->catalog->id => 1]);
    $this->shop->tenant->run(fn () => Order::query()->whereKey($order->id)->update(['status' => OrderStatus::Delivered->value, 'delivered_at' => now()]));
    tenancy()->end();

    $this->actingAsCustomer($customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$order->uuid.'/tracking'))
        ->assertOk()
        ->assertDontSee('data-state="pending"', false)
        ->assertDontSee('data-state="current"', false);
});

it('renders the Elora returns list and favorites', function (): void {
    $customer = shopCustomer($this->shop);
    tenancy()->end();

    $this->actingAsCustomer($customer, $this->shop->tenant)
        ->get(($this->url)('/account/returns'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.returns.index');

    $this->actingAsCustomer($customer, $this->shop->tenant)
        ->get(($this->url)('/account/wishlist'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.favorites.index')
        ->assertSee(__st('elora_pages.favorites.empty_title'));

    $this->shop->tenant->run(fn () => CustomerFavorite::query()->create(['customer_id' => $customer->id, 'store_product_id' => $this->shop->own->id]));

    $this->actingAsCustomer($customer, $this->shop->tenant)
        ->get(($this->url)('/account/wishlist'))
        ->assertOk()
        ->assertSee('data-wishlist-remove="'.$this->shop->own->id.'"', false)
        ->assertSee('data-filter-search="wishlist"', false);
});

it('renders the Elora CMS page and the themed 404', function (): void {
    $this->artisan('storefront:sync-store-pages')->assertSuccessful();

    $this->withSession($this->sa)->get(($this->url)('/pages/contact'))
        ->assertOk()
        ->assertViewIs('storefront::themes.elora.pages.page');

    $this->get(($this->url)('/no-such-page-here'))
        ->assertNotFound()
        ->assertSee('This page doesn’t exist', false)
        ->assertSee('data-theme-family="elora"', false);
});

it('renders the Elora status pages', function (string $view, string $text): void {
    tenancy()->initialize($this->shop->tenant);

    expect(view('storefront::themes.elora.pages.errors.'.$view)->render())->toContain($text)->toContain('elo-wrap');
})->with([
    '500' => ['500', 'Something went wrong'],
    '419' => ['419', 'Your session expired'],
    '403' => ['403', 'You don’t have access to this page'],
    'maintenance' => ['maintenance', 'We’ll be back shortly'],
    'suspended' => ['suspended', 'This store is temporarily unavailable'],
    'not launched' => ['not-launched', 'Opening shortly'],
]);
