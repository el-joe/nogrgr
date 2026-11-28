<?php

use App\Modules\AfterSales\Models\ReturnPolicy;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Engagement\Models\ProductReview;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Storefront account order + return pages (SF-ECO-024/027/028/029/030/031/032): owner 200,
| guests → sign in, other customers → 404; cancel and reviews through the Store API the pages
| post to; return create / reply / withdraw from the pages.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    tenancy()->end();
    $this->url = fn (string $path): string => $this->tenantUrl($this->shop->tenant, $path);
    $this->markOrder = function (OrderStatus $status): void {
        $this->shop->tenant->run(fn () => Order::query()->whereKey($this->order->id)->update(['status' => $status->value, 'delivered_at' => $status === OrderStatus::Delivered ? now() : null]));
    };
});

it('renders every order page for its owner', function (string $path, string $hook): void {
    $path = str_replace('{uuid}', $this->order->uuid, $path);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)($path))
        ->assertOk()
        ->assertSee($hook, false)
        ->assertSee($this->order->number);
})->with([
    'orders' => ['/account/orders', 'data-order-card'],
    'orders tab' => ['/account/orders?status=processing', 'data-order-card'],
    'details' => ['/orders/{uuid}', 'data-order-actions'],
    'tracking' => ['/orders/{uuid}/tracking', 'data-panel="tracking"'],
    'success' => ['/orders/{uuid}?placed=1', 'data-order-success'],
    'invoice' => ['/orders/{uuid}/invoice', 'data-print-invoice'],
]);

it('sends guests to sign in', function (string $path): void {
    $this->get(($this->url)(str_replace('{uuid}', $this->order->uuid, $path)))->assertRedirect();
})->with(['/account/orders', '/orders/{uuid}', '/orders/{uuid}/tracking', '/orders/{uuid}/invoice', '/orders/{uuid}/return', '/account/returns', '/account/returns/1', '/account/wishlist']);

it('hides other customers orders and returns', function (): void {
    $other = shopCustomer($this->shop);
    tenancy()->end();

    foreach (['/orders/'.$this->order->uuid, '/orders/'.$this->order->uuid.'/tracking', '/orders/'.$this->order->uuid.'/invoice', '/orders/'.$this->order->uuid.'/return', '/account/returns/999'] as $path) {
        $this->actingAsCustomer($other, $this->shop->tenant)->get(($this->url)($path))->assertNotFound();
    }

    $this->actingAsCustomer($other, $this->shop->tenant)
        ->get(($this->url)('/account/orders'))
        ->assertOk()
        ->assertDontSee($this->order->number)
        ->assertSee(__st('orders.empty_title'));
});

it('filters by tab and search with an empty state', function (): void {
    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/orders?status=delivered'))
        ->assertOk()
        ->assertDontSee('data-order-card="'.$this->order->uuid.'"', false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/orders?q=nothing-like-this'))
        ->assertOk()
        ->assertSee(__st('orders.no_results_title'));
});

it('cancels a paid order from the details page actions', function (): void {
    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid))
        ->assertSee('data-cancel-order', false)
        ->assertSee(route('store-api.v1.orders.cancel', $this->order->uuid, false), false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->postJson(($this->url)('/store-api/v1/orders/'.$this->order->uuid.'/cancel'), ['reason' => 'Changed my mind'])
        ->assertOk();

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid))
        ->assertOk()
        ->assertDontSee('data-cancel-order', false);
});

it('offers reviews only on delivered lines', function (): void {
    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid))
        ->assertDontSee('data-review=', false);

    ($this->markOrder)(OrderStatus::Delivered);
    $item = $this->order->items->first();

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid))
        ->assertSee('data-review=', false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->postJson(($this->url)('/store-api/v1/orders/'.$this->order->uuid.'/items/'.$item->id.'/review'), ['rating' => 5, 'body' => 'Great sound.'])
        ->assertSuccessful();

    expect($this->shop->tenant->run(fn () => ProductReview::query()->count()))->toBe(1);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid))
        ->assertDontSee('data-review=', false)
        ->assertSee(__st('orders.reviewed'));
});

it('requests, replies to and withdraws a return from the pages', function (): void {
    ReturnPolicy::query()->update(['require_video' => false, 'window_days' => 14, 'non_returnable_category_ids' => null]);
    ($this->markOrder)(OrderStatus::Delivered);
    $item = $this->order->items->first();

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid.'/return'))
        ->assertOk()
        ->assertSee('data-return-form', false)
        ->assertSee(route('store-api.v1.orders.returns.store', $this->order->uuid, false), false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->post(($this->url)('/store-api/v1/orders/'.$this->order->uuid.'/returns'), [
            'reason_code' => 'changed_mind',
            'description' => 'Ordered the wrong colour.',
            'type' => 'return',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $return = ReturnRequest::withoutTenantScope()->latest('id')->firstOrFail();

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/returns'))
        ->assertOk()
        ->assertSee($return->reference)
        ->assertSee('data-filter-item="open"', false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/returns/'.$return->id))
        ->assertOk()
        ->assertSee('data-evidence', false)
        ->assertSee('sf-withdraw-return', false);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->postJson(($this->url)('/store-api/v1/returns/'.$return->id.'/messages'), ['message' => 'Here is more detail about it.'])
        ->assertOk();

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->postJson(($this->url)('/store-api/v1/returns/'.$return->id.'/withdraw'))
        ->assertOk();

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/returns/'.$return->id))
        ->assertOk()
        ->assertSee('Here is more detail about it.')
        ->assertSee(__st('returns.closed_note'))
        ->assertDontSee('sf-withdraw-return', false);
});

it('says the subtotal includes fixed shipping only when a line carries it', function (): void {
    $get = fn () => $this->actingAsCustomer($this->customer, $this->shop->tenant)->get(($this->url)('/orders/'.$this->order->uuid))->assertOk();

    $get()->assertSee(__st('orders.items_subtotal'));

    $this->shop->tenant->run(fn () => OrderItem::query()->where('order_id', $this->order->id)->update(['fixed_shipping' => 0]));

    $get()->assertSee(__st('orders.items_subtotal_plain'))->assertDontSee(__st('orders.items_subtotal'));
});

it('says plainly that every return reason needs a video', function (): void {
    ReturnPolicy::query()->update(['require_video' => true, 'window_days' => 14, 'non_returnable_category_ids' => null]);
    ($this->markOrder)(OrderStatus::Delivered);

    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/orders/'.$this->order->uuid.'/return'))
        ->assertOk()
        ->assertSee(__st('returns.video_required_all'));
});

it('shows the empty returns state', function (): void {
    $this->actingAsCustomer($this->customer, $this->shop->tenant)
        ->get(($this->url)('/account/returns'))
        ->assertOk()
        ->assertSee(__st('returns.empty_title'));
});
