<?php

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Enums\CancellationReason;
use App\Modules\Commerce\Models\Order;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Store API order extras (APP-015): cancellation reasons + cancel with a reason code,
| reorder (available lines back into the cart at today's prices), signed invoice link.
| Golden: one central product in SA = 24.00 + 4.80 profit + 1.20 fixed = 30.00.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
});

it('lists the customer cancellation reasons', function (): void {
    $reasons = $this->getJson(storeApi('orders/cancellation-reasons'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->json('data.reasons');

    expect(array_column($reasons, 'value'))->toBe(CancellationReason::customerValues())
        ->toContain('changed_mind', 'other')
        ->not->toContain('suspected_fraud');
});

it('cancels with a reason code and requires a note for other', function (): void {
    $uuid = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1])['orders'][0]['uuid'];

    $this->postJson(storeApi('orders/'.$uuid.'/cancel'), ['reason_code' => 'suspected_fraud'], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()->assertJsonValidationErrors('reason_code');
    $this->postJson(storeApi('orders/'.$uuid.'/cancel'), ['reason_code' => 'other'], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->postJson(storeApi('orders/'.$uuid.'/cancel'), ['reason_code' => 'found_better_price', 'reason' => 'Cheaper nearby'], shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($this->shop->tenant->run(fn () => Order::query()->where('uuid', $uuid)->sole()->cancel_reason_code))->toBe(CancellationReason::FoundBetterPrice);
});

it('reorders the available lines into the cart and reports the rest', function (): void {
    $uuid = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2, $this->shop->own->id => 1])['orders'][0]['uuid'];
    $this->shop->tenant->run(fn () => StoreProduct::query()->whereKey($this->shop->own->id)->update(['is_active' => false]));

    $response = $this->postJson(storeApi('orders/'.$uuid.'/reorder'), [], shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.added')
        ->assertJsonPath('data.added.0.store_product_id', $this->shop->catalog->id)
        ->assertJsonPath('data.added.0.quantity', 2);

    $lines = collect($response->json('data.cart.lines'))->keyBy('store_product_id');
    // Today's price: 2 × (24.00 + 4.80) + 1.20 fixed shipping once per line = 58.80.
    expect($lines[$this->shop->catalog->id]['total']['amount'])->toBe(5880)
        ->and($lines[$this->shop->catalog->id]['total']['currency'])->toBe('USD');

    // The own product (separate order of the split checkout) is switched off: nothing to add.
    $ownOrder = $this->shop->tenant->run(fn () => Order::query()->whereHas('items', fn ($query) => $query->where('store_product_id', $this->shop->own->id))->sole()->uuid);
    $this->postJson(storeApi('orders/'.$ownOrder.'/reorder'), [], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()->assertJsonPath('data.reason', 'reorder_unavailable');
});

it('needs the own order to reorder or get an invoice', function (): void {
    $uuid = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1])['orders'][0]['uuid'];

    shopActingAs(shopCustomer($this->shop));
    $this->postJson(storeApi('orders/'.$uuid.'/reorder'), [], shopHeaders($this->shop->tenant))->assertNotFound();
    $this->getJson(storeApi('orders/'.$uuid.'/invoice'), shopHeaders($this->shop->tenant))->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->getJson(storeApi('orders/'.$uuid.'/invoice'), shopHeaders($this->shop->tenant))->assertUnauthorized();
});

it('returns a temporary signed invoice link on the store domain', function (): void {
    $uuid = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1])['orders'][0]['uuid'];

    $url = $this->getJson(storeApi('orders/'.$uuid.'/invoice'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'expires_at']])
        ->json('data.url');

    expect($url)->toContain('/orders/'.$uuid.'/invoice/print')->toContain('signature=');

    $this->app['auth']->forgetGuards();
    $path = (string) parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    $this->get($this->tenantUrl($this->shop->tenant, $path))->assertOk()->assertSee($this->shop->tenant->run(fn () => Order::query()->where('uuid', $uuid)->sole()->number));
    $this->get($this->tenantUrl($this->shop->tenant, '/orders/'.$uuid.'/invoice/print?signature=forged'))->assertForbidden();
});
