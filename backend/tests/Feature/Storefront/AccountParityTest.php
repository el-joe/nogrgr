<?php

use App\Modules\AfterSales\Enums\ReturnMethod;
use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Commerce\Enums\CancellationReason;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StockReservation;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Shared\Money\Money;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Account / order pages parity with the old project (release-qa/order-lifecycle-round2.md):
| order details sections, return form fields + estimate + exchange options, atomic return
| evidence, cancel reason codes, saved addresses on the profile, stock holds on refunds.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    tenancy()->end();
    ReturnPolicy::query()->update(['require_video' => false, 'window_days' => 14, 'non_returnable_category_ids' => null]);
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->page = fn (string $path) => $this->actingAsCustomer($this->customer, $this->shop->tenant)->get(($this->url)($path));
    $this->api = fn (string $path, array $data) => $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->post(($this->url)('/store-api/v1/'.$path), $data, ['Accept' => 'application/json']);
    $this->deliver = fn () => $this->shop->tenant->run(fn () => Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Delivered->value, 'delivered_at' => now()]));
    $this->payload = fn (array $overrides = []): array => array_replace([
        'reason_code' => 'changed_mind',
        'description' => 'Ordered the wrong colour.',
        'type' => 'return',
        'return_method' => 'drop_off',
        'customer_note' => 'After 5pm please.',
        'items' => [['order_item_id' => $this->order->items->first()->id, 'quantity' => 1]],
    ], $overrides);
});

it('shows the old order detail sections and the steps still to come', function (): void {
    ($this->page)('/orders/'.$this->order->uuid)
        ->assertOk()
        ->assertSee('data-order-payment', false)
        ->assertSee('data-order-activity', false)
        ->assertSee('data-order-help', false)
        ->assertSee(__st('orders.timeline.expected'))
        ->assertSee(__st('orders.timeline.delivered'))
        ->assertDontSee('data-order-cancellation', false);

    ($this->api)('orders/'.$this->order->uuid.'/cancel', ['reason_code' => 'changed_mind'])->assertOk();

    ($this->page)('/orders/'.$this->order->uuid)
        ->assertOk()
        ->assertSee('data-order-cancellation', false)
        ->assertSee(CancellationReason::ChangedMind->label())
        ->assertSee(__st('orders.cancelled_by_you'))
        ->assertSee('data-order-refunds', false)
        ->assertDontSee(__st('orders.timeline.expected'));
});

it('offers the customer reason codes and needs a note for "other"', function (): void {
    ($this->page)('/orders/'.$this->order->uuid)->assertSee('value="duplicate_order"', false)->assertSee('name="reason_code"', false);

    ($this->api)('orders/'.$this->order->uuid.'/cancel', ['reason_code' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    ($this->api)('orders/'.$this->order->uuid.'/cancel', ['reason_code' => 'other', 'reason' => 'Bought it twice'])->assertOk();

    expect(orderIn($this->shop, $this->order)->cancel_reason_code)->toBe(CancellationReason::Other);
});

it('makes the units of a refunded, never shipped order sellable again', function (): void {
    $this->shop->tenant->run(function (): void {
        app(OrderLifecycleService::class)->transition(Order::query()->sole(), OrderStatus::Refunded);
    });

    expect(StockReservation::query()->pluck('status')->unique()->all())->toBe([StockReservationStatus::Released]);
});

it('returns own stock when a paid order is cancelled', function (): void {
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->own->id => 2], 'order-key-own1');
    $stock = fn (): int => $this->shop->tenant->run(fn (): int => (int) $this->shop->own->fresh()->stock);
    expect($stock())->toBe(3);

    $this->shop->tenant->run(fn () => app(OrderLifecycleService::class)->transition(Order::query()->whereKey($order->id)->sole(), OrderStatus::Cancelled));

    expect($stock())->toBe(5);
});

it('keeps the return method and note and estimates what create refunds', function (): void {
    ($this->deliver)();

    $estimate = ($this->api)('orders/'.$this->order->uuid.'/returns/estimate', ($this->payload)())->assertOk()->json('data.total.amount');
    ($this->api)('orders/'.$this->order->uuid.'/returns', ($this->payload)())->assertCreated();
    $return = ReturnRequest::withoutTenantScope()->sole();

    expect($return->return_method)->toBe(ReturnMethod::DropOff)
        ->and($return->customer_note)->toBe('After 5pm please.')
        ->and($return->refund_amount)->toBe($estimate);

    ($this->page)('/account/returns/'.$return->id)->assertOk()->assertSee(ReturnMethod::DropOff->label())->assertSee('After 5pm please.');
    ($this->page)('/account/returns')->assertOk()->assertSee('data-return-order', false);
    ($this->page)('/orders/'.$this->order->uuid)->assertSee('data-item-return', false);
    ($this->page)('/orders/'.$this->order->uuid.'/return')->assertOk()->assertSee('name="return_method"', false)->assertSee('name="customer_note"', false)->assertSee('data-return-estimate', false);
    tenancy()->end(); // the storefront requests leave the store initialised; admin roles are central
    $this->actingAs(commerceAdmin(), 'admin')->get($this->centralUrl('/admin/returns/'.$return->id))->assertOk()->assertSee('After 5pm please.');
});

it('leaves no return behind when its evidence cannot be stored', function (): void {
    ($this->deliver)();
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('putFileAs')->andReturn(false);
    Storage::set('central', $disk);

    ($this->api)('orders/'.$this->order->uuid.'/returns', ($this->payload)(['photos' => [Illuminate\Http\UploadedFile::fake()->image('a.jpg')]]))
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'upload_failed');

    expect(ReturnRequest::withoutTenantScope()->count())->toBe(0);
});

it('offers only same-price options of the same product for an exchange', function (): void {
    [$small, $large, $dearer] = $this->shop->tenant->run(fn (): array => array_map(
        fn (array $row): StoreProductVariant => StoreProductVariant::query()->create(['store_product_id' => $this->shop->own->id, 'title' => ['en' => $row[0]], 'price' => Money::of($row[1]), 'stock' => 5, 'is_active' => true]),
        [['Small', '15.00'], ['Large', '15.00'], ['Gold', '25.00']],
    ));
    tenancy()->end();
    shopActingAs($this->customer);
    $this->postJson(storeApi('cart/items'), ['store_product_id' => $this->shop->own->id, 'variant_id' => $small->id], shopHeaders($this->shop->tenant))->assertCreated();
    $placed = $this->postJson(storeApi('checkout/place'), ['gateway' => 'stripe'], shopHeaders($this->shop->tenant) + ['Idempotency-Key' => 'order-key-exch'])->assertCreated()->json('data');
    tenancy()->end();
    $this->shop->tenant->run(fn () => Order::query()->where('uuid', $placed['orders'][0]['uuid'])->update(['status' => OrderStatus::Delivered->value, 'delivered_at' => now()]));
    $uuid = $placed['orders'][0]['uuid'];

    $options = $this->getJson(storeApi("orders/{$uuid}/returns/options"), shopHeaders($this->shop->tenant))->assertOk()->json('data.lines.0');
    expect(array_column($options['exchange_options'], 'id'))->toBe([$large->id]);

    $items = fn (int $variant): array => ($this->payload)(['type' => 'exchange', 'items' => [['order_item_id' => $options['order_item_id'], 'quantity' => 1, 'replacement_variant_id' => $variant]]]);
    $this->postJson(storeApi("orders/{$uuid}/returns"), $items($dearer->id), shopHeaders($this->shop->tenant))->assertUnprocessable()->assertJsonPath('data.reason', 'replacement_unavailable');
    $this->postJson(storeApi("orders/{$uuid}/returns"), $items($large->id), shopHeaders($this->shop->tenant))->assertCreated()->assertJsonPath('data.returns.0.replacement_label', 'Large');
});

it('lists the saved addresses on the profile page', function (): void {
    ($this->page)('/account/profile')->assertOk()->assertSee('data-profile-addresses', false)->assertSee('data-address-book', false);
});
