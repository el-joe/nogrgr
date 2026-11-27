<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Localization\Models\Currency;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Models\StorePage;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Commerce/StoreApiHelpers.php';

/*
| Store API catalog (decision #106): home, categories, products, product details, search,
| autocomplete, CMS pages and the shell — on the shopSetup store (SA + AE): central product
| 24.00 + 20 % markup + 1.20 SA fixed shipping = $30.00 (3000) in SA; own product $15.00.
*/

function apiCatalogShop(string $slug = 'shop'): object
{
    $shop = shopSetup($slug);
    $category = Category::factory()->create(['slug' => 'gadgets-'.$slug, 'name' => 'Gadgets']);
    $shop->central->categories()->attach($category->id);
    $shop->tenant->run(function () use ($shop, $category): void {
        $shop->own->forceFill(['name' => 'Desk Lamp', 'category_ids' => [$category->id]])->save();
    });
    $shop->category = $category;

    return $shop;
}

/**
 * @return array<string, string>
 */
function catalogHeaders(object $shop, array $extra = []): array
{
    return shopHeaders($shop->tenant) + ['X-Country' => 'SA'] + $extra;
}

it('lists products with storefront prices in USD (golden values)', function (): void {
    $shop = apiCatalogShop();

    $response = $this->getJson(storeApi('products?sort=price_desc'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.sort', 'price_desc')
        ->assertJsonPath('data.items.0.id', $shop->catalog->id)
        ->assertJsonPath('data.items.0.price.amount', 3000)
        ->assertJsonPath('data.items.0.price.currency', 'USD')
        ->assertJsonPath('data.items.0.price.formatted', '$30.00')
        ->assertJsonPath('data.items.1.name', 'Desk Lamp')
        ->assertJsonPath('data.items.1.price.amount', 1500)
        ->assertJsonPath('data.items.1.favorited', false);

    expect($response->json('data.facets'))->toHaveKeys(['price', 'rating', 'options', 'categories', 'in_stock']);
});

it('filters by price, category and paginates', function (): void {
    $shop = apiCatalogShop();

    $this->getJson(storeApi('products?price_max=2000'), catalogHeaders($shop))
        ->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.items.0.name', 'Desk Lamp');

    $this->getJson(storeApi('products?category='.$shop->category->slug.'&per_page=1'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.meta.per_page', 1)
        ->assertJsonPath('data.meta.last_page', 2)
        ->assertJsonCount(1, 'data.items');

    $this->getJson(storeApi('products?category=nope-nope'), catalogHeaders($shop))->assertNotFound();
});

it('validates the listing filters', function (): void {
    $shop = apiCatalogShop();

    $this->getJson(storeApi('products?per_page=500&sort=cheapest&rating=9&price_min=500&price_max=100'), catalogHeaders($shop))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page', 'sort', 'rating', 'price_max']);
});

it('prices a live flash sale exactly like the cart', function (): void {
    $shop = apiCatalogShop();
    FlashSale::factory()->create(['discount_percentage' => 50])->products()->sync([$shop->central->id]);
    Cache::flush();

    $listed = $this->getJson(storeApi('products?flash=1'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->json('data.items.0');

    expect($listed['flash']['percentage'])->toEqual(50.0)
        ->and($listed['compare_price']['amount'])->toBe(3000);

    $cart = $this->postJson(storeApi('cart/items'), ['store_product_id' => $shop->catalog->id, 'quantity' => 1], shopHeaders($shop->tenant))->assertCreated();

    // 30.00 storefront price - 50 % flash = 15.00, the cart line total of one unit.
    expect($listed['price']['amount'])->toBe(1500)
        ->and($cart->json('data.lines.0.total.amount'))->toBe($listed['price']['amount'])
        ->and($cart->json('data.lines.0.unit_price.amount'))->toBe($listed['compare_price']['amount']);
});

it('converts the display currency but keeps USD amounts', function (): void {
    $shop = apiCatalogShop();
    Currency::query()->where('code', 'SAR')->delete();
    Currency::factory()->code('SAR', '3.750000')->create(['is_active' => true, 'rate_synced_at' => now()]);

    $this->getJson(storeApi('products?sort=price_desc'), catalogHeaders($shop, ['X-Currency' => 'SAR']))
        ->assertOk()
        ->assertJsonPath('data.items.0.price.amount', 3000)
        ->assertJsonPath('data.items.0.price.currency', 'USD')
        ->assertJsonPath('data.items.0.price.display.currency', 'SAR')
        ->assertJsonPath('data.items.0.price.display.amount', 112.5);
});

it('hides products the store does not sell in the requested country', function (): void {
    $shop = apiCatalogShop();
    $shop->tenant->run(fn () => $shop->own->forceFill(['country_ids' => [$shop->ae->id]])->save());

    $this->getJson(storeApi('products'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.meta.total', 1);
    $this->getJson(storeApi('products/'.$shop->own->id), catalogHeaders($shop))->assertNotFound();
});

it('shows product details with variants, delivery and review summary', function (): void {
    $shop = apiCatalogShop();

    $this->getJson(storeApi('products/'.$shop->catalog->id), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('data.id', $shop->catalog->id)
        ->assertJsonPath('data.price.amount', 3000)
        ->assertJsonPath('data.price.currency', 'USD')
        ->assertJsonPath('data.in_stock', true)
        ->assertJsonPath('data.category.slug', $shop->category->slug)
        ->assertJsonPath('data.reviews.count', 0)
        ->assertJsonStructure(['data' => ['images', 'attributes', 'variants', 'delivery', 'delivery_days', 'free_shipping_grams', 'related', 'breadcrumbs']]);

    $this->getJson(storeApi('products/999999'), catalogHeaders($shop))->assertNotFound();
});

it('marks favourites of the signed-in customer', function (): void {
    $shop = apiCatalogShop();
    $customer = shopCustomer($shop);
    $this->postJson('/api/mobile/v1/favorites', ['store_product_id' => $shop->own->id], shopHeaders($shop->tenant) + ['Authorization' => 'Bearer '.$shop->tenant->run(fn () => $customer->createToken('mobile:t', ['api'])->plainTextToken)])->assertSuccessful();

    Sanctum::actingAs($customer, ['api'], 'sanctum');

    $this->getJson(storeApi('products/'.$shop->own->id), catalogHeaders($shop))->assertOk()->assertJsonPath('data.favorited', true);
    $this->getJson(storeApi('products?price_max=2000'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.items.0.favorited', true);
});

it('returns the category tree and one category with breadcrumbs', function (): void {
    $shop = apiCatalogShop();
    $child = Category::factory()->create(['slug' => 'phones-shop', 'name' => 'Phones', 'parent_id' => $shop->category->id]);
    $shop->central->categories()->attach($child->id);
    Cache::flush();

    $this->getJson(storeApi('categories'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('data.items.0.slug', $shop->category->slug)
        ->assertJsonPath('data.items.0.children.0.slug', 'phones-shop');

    $this->getJson(storeApi('categories/phones-shop'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('data.category.name', 'Phones')
        ->assertJsonPath('data.breadcrumbs.0.slug', $shop->category->slug);

    $this->getJson(storeApi('categories/unknown'), catalogHeaders($shop))->assertNotFound();
});

it('returns the home sections of the store theme and home tabs', function (): void {
    $shop = apiCatalogShop();

    $this->getJson(storeApi('home'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonPath('data.country.iso2', 'SA')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.sections.new_in.meta.total', 2)
        ->assertJsonPath('data.sections.tabs.0.key', 'recommended')
        ->assertJsonStructure(['data' => ['theme' => ['slug', 'family', 'variant'], 'sections' => ['hero', 'promo', 'deals', 'clearance', 'interests', 'flash_sale', 'best_selling', 'top_rated']]]);

    $this->getJson(storeApi('home/tabs/recommended'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.meta.total', 2);
    $this->getJson(storeApi('home/tabs/'.$shop->category->slug), catalogHeaders($shop))->assertOk();
    $this->getJson(storeApi('home/tabs/unknown-tab'), catalogHeaders($shop))->assertNotFound();
});

it('searches with filters and suggests products and categories', function (): void {
    $shop = apiCatalogShop();

    $this->getJson(storeApi('search?q=lamp'), catalogHeaders($shop))
        ->assertOk()->assertJsonPath('data.term', 'lamp')->assertJsonPath('data.items.0.name', 'Desk Lamp')->assertJsonPath('data.popular', []);
    $this->getJson(storeApi('search?q=zzzz-nothing'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.meta.total', 0);
    $this->getJson(storeApi('search'), catalogHeaders($shop))->assertUnprocessable()->assertJsonValidationErrors('q');

    $this->getJson(storeApi('search/autocomplete?q=desk'), catalogHeaders($shop))
        ->assertOk()->assertJsonPath('data.products.0.name', 'Desk Lamp')->assertJsonPath('data.products.0.price.amount', 1500);
    $this->getJson(storeApi('search/autocomplete?q=gadg'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.categories.0.name', 'Gadgets');
    $this->getJson(storeApi('search/autocomplete?q=d'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.products', []);
});

it('serves published CMS pages and the shell', function (): void {
    $shop = apiCatalogShop();
    $shop->tenant->run(function (): void {
        StorePage::query()->create(['slug' => 'about', 'type' => StorePageType::System, 'title' => 'About us', 'body' => '<p>Hello</p>', 'is_active' => true, 'show_in_footer' => true, 'sort_order' => 1]);
        StorePage::query()->create(['slug' => 'hidden-page', 'type' => StorePageType::Custom, 'title' => 'Hidden', 'body' => 'x', 'is_active' => false, 'show_in_footer' => false, 'sort_order' => 2]);
    });

    $this->getJson(storeApi('pages'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.items.0.slug', 'about')->assertJsonMissingPath('data.items.0.body');
    $this->getJson(storeApi('pages/about'), catalogHeaders($shop))->assertOk()->assertJsonPath('data.body', '<p>Hello</p>');
    $this->getJson(storeApi('pages/hidden-page'), catalogHeaders($shop))->assertNotFound();

    $this->getJson(storeApi('shell'), catalogHeaders($shop))
        ->assertOk()
        ->assertJsonStructure(['data' => ['header_links', 'menu', 'footer' => ['columns', 'legal', 'about', 'copyright'], 'social', 'promo_bar', 'payment_badges']]);
});

it('keeps stores isolated', function (): void {
    $shop = apiCatalogShop('shop');
    $other = $this->createTenant('other-shop');

    $this->getJson(storeApi('products/'.$shop->own->id), shopHeaders($other) + ['X-Country' => 'SA'])->assertNotFound();
    $this->getJson(storeApi('products'), ['X-Tenant' => 'missing-store', 'Accept' => 'application/json'])->assertNotFound();
});
