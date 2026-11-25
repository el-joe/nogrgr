<?php

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Commerce\Pricing\OrderComposer;
use App\Modules\Commerce\Services\Cart\CartService;
use App\Shared\Money\Money;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Store API cart (P5 batch 2): guests by X-Cart-Token, customers by token, merge on login,
| validation (inactive, country, stock), coupon / country switch, totals from OrderComposer.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
});

it('lets a guest build a cart priced by OrderComposer (golden values)', function (): void {
    $response = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.country_id', $this->shop->sa->id)
        ->assertJsonPath('data.lines.0.unit_price.amount', 3000)
        ->assertJsonPath('data.totals.subtotal.amount', 5880)
        ->assertJsonPath('data.totals.items_total.amount', 5880)
        ->assertJsonPath('data.totals.shipping.amount', 500)
        ->assertJsonPath('data.totals.grand_total.amount', 6380)
        ->assertJsonPath('data.shipping.zone', 'All KSA')
        ->assertJsonPath('data.free_shipping.threshold_grams', 1001)
        ->assertJsonPath('data.free_shipping.weight_grams', 600)
        ->assertJsonPath('data.can_checkout', true);

    expect($response->json('data.delivery.min_days'))->toBeInt();

    $token = $response->json('data.token');
    expect($token)->toBeString();

    $this->getJson(storeApi('cart'), shopHeaders($this->shop->tenant, $token))
        ->assertOk()
        ->assertJsonPath('data.items_count', 2)
        ->assertJsonPath('data.totals.grand_total.amount', 6380);

    // No cart token: an empty cart.
    $this->getJson(storeApi('cart'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.items_count', 0)
        ->assertJsonPath('data.token', null);
});

it('splits a mixed cart like OrderComposer and keeps own lines out of shipping', function (): void {
    $token = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant))->json('data.token');

    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->own->id], shopHeaders($this->shop->tenant, $token))
        ->assertCreated()
        ->assertJsonPath('data.split', true)
        ->assertJsonPath('data.orders.0.grand_total.amount', 6380)
        ->assertJsonPath('data.orders.1.grand_total.amount', 1500)
        ->assertJsonPath('data.orders.1.shipping.amount', 0)
        ->assertJsonPath('data.totals.grand_total.amount', 7880)
        ->assertJsonPath('data.free_shipping.weight_grams', 600);
});

it('refuses inactive products, unsold countries and missing stock', function (): void {
    $headers = shopHeaders($this->shop->tenant);

    $this->shop->tenant->run(fn () => $this->shop->own->forceFill(['is_active' => false])->save());
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->own->id], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'inactive')
        ->assertJsonPath('message', __('commerce::cart.issues.inactive'));

    $this->shop->central->update(['allowed_country_ids' => [$this->shop->ae->id]]);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'not_sold_in_country');

    $this->shop->central->update(['allowed_country_ids' => null, 'stock' => 1]);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'insufficient_stock')
        ->assertJsonPath('data.available', 1);

    $this->postJson(storeApi('cart/items'), ['store_product_id' => 999999], $headers)->assertNotFound();
});

it('requires a variant for variable products and validates the input', function (): void {
    $variant = ProductVariant::factory()->create(['product_id' => $this->shop->central->id, 'price' => Money::of('26.00'), 'stock' => 3]);
    $headers = shopHeaders($this->shop->tenant);

    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'variant_unavailable');

    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'variant_id' => $variant->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.lines.0.variant_id', $variant->id)
        ->assertJsonPath('data.lines.0.unit_price.amount', 26 * 120 + 120);

    $this->postJson(storeApi('cart/items'), ['quantity' => 0], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['store_product_id', 'quantity']);
});

it('updates and removes lines and switches the country', function (): void {
    $data = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], shopHeaders($this->shop->tenant))->json('data');
    $headers = shopHeaders($this->shop->tenant, $data['token']);
    $item = $data['lines'][0]['id'];

    $this->patchJson(storeApi('cart/items/'.$item), ['quantity' => 4], $headers)
        ->assertOk()
        ->assertJsonPath('data.items_count', 4)
        ->assertJsonPath('data.shipping.free', true)
        ->assertJsonPath('data.free_shipping.reached', true);

    $this->putJson(storeApi('cart/country'), ['country_id' => $this->shop->ae->id], $headers)
        ->assertOk()
        ->assertJsonPath('data.country_id', $this->shop->ae->id)
        ->assertJsonPath('data.shipping.available', false)
        ->assertJsonPath('data.shipping.reason', 'country_not_served')
        ->assertJsonPath('data.can_checkout', false);

    $this->putJson(storeApi('cart/country'), ['country_id' => 999999], $headers)->assertUnprocessable();

    $this->deleteJson(storeApi('cart/items/'.$item), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.items_count', 0);

    $this->patchJson(storeApi('cart/items/'.$item), ['quantity' => 1], $headers)->assertNotFound();
});

it('applies flash sales and a coupon from OrderComposer', function (): void {
    $this->shop->tenant->run(function (): void {
        $sale = StoreFlashSale::factory()->create(['discount_percentage' => 10, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $sale->productRows()->create(['product_source' => 'central', 'product_id' => $this->shop->central->id]);
        StoreCoupon::factory()->create(['code' => 'SAVE5', 'type' => 'fixed', 'amount_minor' => 500, 'percentage' => null, 'min_spend_minor' => 0]);
    });

    $token = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant))->json('data.token');
    $headers = shopHeaders($this->shop->tenant, $token);

    // discount = (57.60 + 1.20) × 10 % = 5.88; items 52.92; + 5.00 shipping.
    $this->getJson(storeApi('cart'), $headers)
        ->assertJsonPath('data.totals.flash_discount.amount', 588)
        ->assertJsonPath('data.totals.items_total.amount', 5292)
        ->assertJsonPath('data.lines.0.flash_sale.source', 'vendor');

    $this->postJson(storeApi('cart/coupon'), ['code' => 'nope'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'coupon_rejected');

    $this->postJson(storeApi('cart/coupon'), ['code' => 'save5'], $headers)
        ->assertOk()
        ->assertJsonPath('data.coupon.applied', true)
        ->assertJsonPath('data.coupon.code', 'SAVE5')
        ->assertJsonPath('data.totals.coupon_discount.amount', 500)
        ->assertJsonPath('data.totals.grand_total.amount', 5292 + 500 - 500);

    $this->deleteJson(storeApi('cart/coupon'), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.coupon', null)
        ->assertJsonPath('data.totals.grand_total.amount', 5792);
});

it('merges the guest cart into the customer cart on sign-in', function (): void {
    $customer = shopCustomer($this->shop);
    shopActingAs($customer);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], shopHeaders($this->shop->tenant))->assertCreated();

    $this->app['auth']->forgetGuards();
    $guestToken = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant))->json('data.token');
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->own->id], shopHeaders($this->shop->tenant, $guestToken))->assertCreated();

    shopActingAs($customer);
    $this->getJson(storeApi('cart'), shopHeaders($this->shop->tenant, $guestToken))
        ->assertOk()
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.quantity', 3)
        ->assertJsonPath('data.lines.1.quantity', 1);

    expect($this->shop->tenant->run(fn () => Cart::query()->count()))->toBe(1);
});

it('isolates carts per store', function (): void {
    $other = shopSetup('othershop');
    $token = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], shopHeaders($this->shop->tenant))->json('data.token');

    $this->getJson(storeApi('cart'), shopHeaders($other->tenant, $token))
        ->assertOk()
        ->assertJsonPath('data.items_count', 0);

    $this->getJson(storeApi('cart'), ['X-Tenant' => '999999', 'Accept' => 'application/json'])->assertNotFound();
});

it('matches the OrderComposer golden result end to end', function (): void {
    $data = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant))->json('data');

    $expected = $this->shop->tenant->run(function () use ($data) {
        $cart = Cart::query()->where('token', $data['token'])->firstOrFail();

        return app(CartService::class)->summaryOf($cart, null)->composed;
    });

    expect($expected->primary?->platformProfit->minor)->toBe(5420)
        ->and($expected->primary?->vendorProfit->minor)->toBe(960)
        ->and($expected->grandTotal()->minor)->toBe($data['totals']['grand_total']['amount'])
        ->and(OrderComposer::composeWithQuote([], $expected->shipping)->isEmpty())->toBeTrue();
});

it('serves themes on the store domain with the cart kept in the session', function (): void {
    $url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, '/store-api/v1/'.$path);

    $this->postJson($url('cart/items'), ['store_product_id' => $this->shop->catalog->id])->assertCreated();

    $this->getJson($url('cart'))
        ->assertOk()
        ->assertJsonPath('data.items_count', 1);

    $customer = shopCustomer($this->shop);
    $this->actingAsCustomer($customer, $this->shop->tenant)
        ->getJson($url('cart'))
        ->assertOk()
        ->assertJsonPath('data.items_count', 1);

    $this->getJson('http://unknown-store.localhost/store-api/v1/cart')->assertNotFound();
});
