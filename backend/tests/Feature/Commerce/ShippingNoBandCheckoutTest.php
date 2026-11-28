<?php

use App\Modules\Commerce\Models\Order;
use App\Modules\Shipping\Models\ShippingZoneBand;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Decision #139: only a price-0 band ships free. A weight no band covers is unavailable in
| the cart, the checkout options and place order. Shop bands: 0–1000 g $5, 1001 g+ $0;
| the central product weighs 300 g.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->capAt = function (int $grams): void {
        ShippingZoneBand::query()->where('name', 'Free')->delete();
        ShippingZoneBand::query()->where('name', 'Standard')->update(['max_weight_grams' => $grams]);
    };
    $this->add = fn (int $storeProductId, int $quantity = 1) => $this->postJson(storeApi('cart/items'), ['store_product_id' => $storeProductId, 'quantity' => $quantity], shopHeaders($this->shop->tenant))->assertCreated();
});

it('blocks the cart and checkout when no band covers the weight', function (): void {
    ($this->capAt)(1000);
    shopActingAs($this->customer);

    ($this->add)($this->shop->catalog->id, 4)
        ->assertJsonPath('data.shipping.available', false)
        ->assertJsonPath('data.shipping.free', false)
        ->assertJsonPath('data.shipping.reason', 'no_band')
        ->assertJsonPath('data.shipping.message', __('shipping::quote.unavailable.no_band'))
        ->assertJsonPath('data.free_shipping', null)
        ->assertJsonPath('data.can_checkout', false);

    $this->getJson(storeApi('checkout/options'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.can_pay', false)
        ->assertJsonPath('data.block', 'shipping_unavailable')
        ->assertJsonPath('data.block_message', __('shipping::quote.unavailable.no_band'));

    $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'no-band-key-1'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('shipping::quote.unavailable.no_band'))
        ->assertJsonPath('data.reason', 'shipping_unavailable');

    expect($this->shop->tenant->run(fn (): int => Order::query()->count()))->toBe(0);
});

it('keeps an own-products-only cart available at 0', function (): void {
    ShippingZoneBand::query()->delete();
    shopActingAs($this->customer);

    ($this->add)($this->shop->own->id)
        ->assertJsonPath('data.shipping.available', true)
        ->assertJsonPath('data.shipping.amount.amount', 0)
        ->assertJsonPath('data.can_checkout', true);
});

it('reports a threshold of 0 as unlocked', function (): void {
    ShippingZoneBand::query()->where('name', 'Standard')->delete();
    ShippingZoneBand::query()->where('name', 'Free')->update(['min_weight_grams' => 0]);
    shopActingAs($this->customer);

    ($this->add)($this->shop->catalog->id)
        ->assertJsonPath('data.free_shipping.threshold_grams', 0)
        ->assertJsonPath('data.free_shipping.reached', true)
        ->assertJsonPath('data.free_shipping.progress', 100)
        ->assertJsonPath('data.shipping.free', true);
});

it('never claims free shipping above a capped free band', function (): void {
    ShippingZoneBand::query()->where('name', 'Standard')->update(['price' => 0]);
    ShippingZoneBand::query()->where('name', 'Free')->update(['price' => 700]);
    shopActingAs($this->customer);

    ($this->add)($this->shop->catalog->id)->assertJsonPath('data.free_shipping.reached', true);
    ($this->add)($this->shop->catalog->id, 3)
        ->assertJsonPath('data.shipping.free', false)
        ->assertJsonPath('data.free_shipping', null);
});
