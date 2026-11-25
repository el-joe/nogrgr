<?php

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Exceptions\OrderTransitionException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/StoreApiHelpers.php';

/*
| Store API customer orders: history, detail, cancel; isolation per customer and store;
| the order state machine.
*/

beforeEach(function (): void {
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
});

it('lists and shows the customer orders with filters', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $uuid = $data['orders'][0]['uuid'];

    $this->getJson(storeApi('orders'), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.uuid', $uuid)
        ->assertJsonPath('data.orders.0.totals.grand_total.amount', 6380)
        ->assertJsonMissingPath('data.orders.0.platform_profit')
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson(storeApi('orders?status=delivered'), shopHeaders($this->shop->tenant))->assertJsonCount(0, 'data.orders');
    $this->getJson(storeApi('orders?search='.$data['orders'][0]['number']), shopHeaders($this->shop->tenant))->assertJsonCount(1, 'data.orders');
    $this->getJson(storeApi('orders?status=lost'), shopHeaders($this->shop->tenant))->assertJsonValidationErrors('status');

    $this->getJson(storeApi('orders/'.$uuid), shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.shipping_address.country_id', $this->shop->sa->id)
        ->assertJsonPath('data.timeline.0.status', 'pending_payment')
        ->assertJsonPath('data.items.0.quantity', 2);
});

it('shows the labels in Arabic', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);

    $this->getJson(storeApi('orders/'.$data['orders'][0]['uuid']), shopHeaders($this->shop->tenant) + ['X-Locale' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.status_label', 'بانتظار الدفع');
});

it('hides other customers and other stores orders', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);
    $uuid = $data['orders'][0]['uuid'];

    shopActingAs(shopCustomer($this->shop));
    $this->getJson(storeApi('orders/'.$uuid), shopHeaders($this->shop->tenant))->assertNotFound();
    $this->getJson(storeApi('orders'), shopHeaders($this->shop->tenant))->assertJsonCount(0, 'data.orders');

    $other = shopSetup('elsewhere');
    shopActingAs(shopCustomer($other));
    $this->getJson(storeApi('orders/'.$uuid), shopHeaders($other->tenant))->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->getJson(storeApi('orders'), shopHeaders($this->shop->tenant))->assertUnauthorized();
});

it('lets the customer cancel before shipping', function (): void {
    Notification::fake();
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);
    $uuid = $data['orders'][0]['uuid'];

    $this->postJson(storeApi('orders/'.$uuid.'/cancel'), ['reason' => 'Changed my mind'], shopHeaders($this->shop->tenant))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellable', false);

    $this->postJson(storeApi('orders/'.$uuid.'/cancel'), [], shopHeaders($this->shop->tenant))
        ->assertUnprocessable()
        ->assertJsonPath('data.reason', 'not_cancellable');

    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn (OrderCustomerNotification $notification): bool => $notification->eventName === 'cancelled');
});

it('enforces the order state machine', function (): void {
    $data = shopPlaceOrder($this->shop, $this->customer, [$this->shop->catalog->id => 1]);

    $this->shop->tenant->run(function () use ($data): void {
        $order = Order::query()->where('uuid', $data['orders'][0]['uuid'])->sole();
        $lifecycle = app(OrderLifecycleService::class);

        expect(fn () => $lifecycle->transition($order, OrderStatus::Shipped))->toThrow(OrderTransitionException::class);

        $lifecycle->markGroupPaid($order->checkout_group, 'stripe');
        $order->refresh();

        foreach ([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Returned] as $status) {
            $lifecycle->transition($order, $status);
        }

        expect($order->status)->toBe(OrderStatus::Returned)
            ->and($order->returned_at)->not->toBeNull()
            ->and($order->history()->pluck('to_status')->map->value->all())->toBe(['pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'completed', 'returned'])
            ->and(fn () => $lifecycle->transition($order, OrderStatus::Paid))->toThrow(OrderTransitionException::class);
    });
});
