<?php

use App\Modules\Billing\Models\PlanLimitOverride;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\StoreCoupon;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Checkout conditions as the old project (docs/plan/release-qa/checkout-parity.md): prices
| follow the shopper's country, the shipping zone follows the saved / selected address, the
| Store API quotes what the pages show, digital products do not ship, a coupon below its
| minimum spend is removed, and the plan's monthly order quota closes the checkout.
| Shop: SA ships ($5 up to 1 kg), AE has no rule; central 24.00 + 20 % + 1.20 fixed in SA.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
});

it('prices for the shopper country but ships to the default address country', function (): void {
    shopActingAs(shopCustomer($this->shop));

    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], shopHeaders($this->shop->tenant) + ['X-Country' => 'AE'])
        ->assertCreated()
        ->assertJsonPath('data.country_id', $this->shop->sa->id)
        // AE prices (no fixed shipping there): 2 x 28.80; SA zone: 5.00.
        ->assertJsonPath('data.totals.items_total.amount', 5760)
        ->assertJsonPath('data.totals.shipping.amount', 500)
        ->assertJsonPath('data.totals.grand_total.amount', 6260);
});

it('keeps the shopper country price and takes the zone from the selected address at checkout', function (): void {
    shopGateway($this->shop->tenant);
    shopActingAs(shopCustomer($this->shop, $this->shop->ae->id));
    $headers = shopHeaders($this->shop->tenant) + ['X-Country' => 'SA'];

    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], $headers)->assertCreated();

    // Default address in AE: no rule there, so shipping is unavailable (old message).
    $this->getJson(storeApi('checkout/options'), $headers)
        ->assertOk()
        ->assertJsonPath('data.cart.shipping.available', false)
        ->assertJsonPath('data.cart.totals.items_total.amount', 5880)
        ->assertJsonPath('data.block', 'shipping_unavailable');
});

it('quotes a theme session for the same country the pages use', function (): void {
    $url = $this->tenantUrl($this->shop->tenant, '/store-api/v1/cart/items');

    // No X-Tenant / X-Country: the visitor header decides, as on the server-rendered cart.
    $this->postJson($url, ['store_product_id' => $this->shop->catalog->id], ['CF-IPCountry' => 'AE'])
        ->assertCreated()
        ->assertJsonPath('data.country_id', $this->shop->ae->id)
        ->assertJsonPath('data.shipping.available', false);
});

it('does not charge or block shipping for a digital product', function (): void {
    $this->shop->central->forceFill(['delivery_scope' => DeliveryScope::Digital])->save();

    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], shopHeaders($this->shop->tenant) + ['X-Country' => 'AE'])
        ->assertCreated()
        ->assertJsonPath('data.shipping.available', true)
        ->assertJsonPath('data.totals.shipping.amount', 0)
        ->assertJsonPath('data.can_checkout', true);
});

it('removes a coupon once the cart falls below its minimum spend', function (): void {
    $this->shop->tenant->run(fn () => StoreCoupon::factory()->create(['code' => 'MIN50', 'type' => 'fixed', 'amount_minor' => 500, 'min_spend_minor' => 5000]));
    $headers = shopHeaders($this->shop->tenant);
    $cart = $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id, 'quantity' => 2], $headers)->json('data');
    $headers = shopHeaders($this->shop->tenant, $cart['token']);
    $this->postJson(storeApi('cart/coupon'), ['code' => 'MIN50'], $headers)->assertOk()->assertJsonPath('data.coupon.applied', true);

    $this->patchJson(storeApi('cart/items/'.$cart['lines'][0]['id']), ['quantity' => 1], $headers)
        ->assertOk()
        ->assertJsonPath('data.coupon.applied', false)
        ->assertJsonPath('data.totals.coupon_discount.amount', 0)
        ->assertJsonPath('data.can_checkout', true);

    expect($this->shop->tenant->run(fn () => Cart::query()->where('token', $cart['token'])->value('coupon_code')))->toBeNull();
});

it('closes the checkout once the plan order quota of the month is used', function (): void {
    shopGateway($this->shop->tenant);
    shopActingAs(shopCustomer($this->shop));
    PlanLimitOverride::query()->create(['tenant_id' => $this->shop->tenant->id, 'limit' => 'orders_per_month', 'value' => 0]);
    $headers = shopHeaders($this->shop->tenant);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->catalog->id], $headers)->assertCreated();

    $this->getJson(storeApi('checkout/options'), $headers)->assertOk()->assertJsonPath('data.block', 'store_order_limit');
    $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], $headers + ['Idempotency-Key' => 'limit-key-0001'])
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'store_order_limit');
});
