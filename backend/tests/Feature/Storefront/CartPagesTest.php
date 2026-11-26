<?php

use App\Modules\Commerce\Models\Cart;

require_once __DIR__.'/../Commerce/StoreApiHelpers.php';

/*
| Cart page, cart fragments and the header mini cart (sf-checkout, SF-ECO-020/021/022).
| Store: shopSetup() (SA + AE; central product $30.00 in SA, own product $15.00).
*/

function cartShopAdd(object $shop, int $productId, int $quantity = 1): void
{
    test()->postJson(test()->tenantUrl($shop->tenant, '/store-api/v1/cart/items'), ['store_product_id' => $productId, 'quantity' => $quantity])->assertCreated();
}

it('shows the empty cart with the trending link and an empty summary', function (): void {
    $shop = shopSetup();

    $this->withSession(['storefront.country_id' => $shop->sa->id])
        ->get($this->tenantUrl($shop->tenant, '/cart'))
        ->assertOk()
        ->assertSee('data-cart-page', false)
        ->assertSee('Your shopping cart is empty')
        ->assertSee('See trending items')
        ->assertSee('Order Summary')
        ->assertSee('sf-mini-cart', false);
});

it('renders the cart lines, summary, free shipping progress and checkout link for the session cart', function (): void {
    $shop = shopSetup();
    $this->withSession(['storefront.country_id' => $shop->sa->id]);
    cartShopAdd($shop, $shop->catalog->id, 2);

    $this->get($this->tenantUrl($shop->tenant, '/cart'))
        ->assertOk()
        ->assertSee('data-cart-line=', false)
        ->assertSee('data-swipe-remove', false)
        ->assertSee('Select all')
        ->assertSee('Qty 2')
        ->assertSee('$63.80 to checkout')
        ->assertSee('data-cart-progress', false)
        ->assertSee('/checkout"', false);
});

it('answers the cart and mini cart fragments as JSON html', function (): void {
    $shop = shopSetup();
    $this->withSession(['storefront.country_id' => $shop->sa->id]);
    cartShopAdd($shop, $shop->own->id);

    $this->getJson($this->tenantUrl($shop->tenant, '/cart/fragment'))
        ->assertOk()
        ->assertJsonPath('data.items_count', 1)
        ->assertJsonPath('data.empty', false)
        ->assertJson(fn ($json) => $json->where('data.html', fn ($html) => str_contains($html, 'data-cart-line'))->etc());

    $this->getJson($this->tenantUrl($shop->tenant, '/cart/mini'))
        ->assertOk()
        ->assertJsonPath('data.items_count', 1)
        ->assertJson(fn ($json) => $json->where('data.html', fn ($html) => str_contains($html, 'Go to cart'))->etc());
});

it('gives a cart without a country the storefront context country before pricing', function (): void {
    $shop = shopSetup();
    $this->withSession(['storefront.country_id' => $shop->ae->id]);
    cartShopAdd($shop, $shop->own->id);
    $shop->tenant->run(fn () => Cart::query()->update(['country_id' => null]));

    $this->getJson($this->tenantUrl($shop->tenant, '/cart/fragment'))->assertOk();

    expect($shop->tenant->run(fn () => Cart::query()->value('country_id')))->toBe($shop->ae->id);
});

it('shows the arabic cart right to left', function (): void {
    $shop = shopSetup();

    $this->withSession(['storefront.country_id' => $shop->sa->id])
        ->get($this->tenantUrl($shop->tenant, '/cart?lang=ar'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('سلة التسوق فارغة');
});
