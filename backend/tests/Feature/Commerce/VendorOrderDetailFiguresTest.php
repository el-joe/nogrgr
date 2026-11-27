<?php

use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Commerce\Models\OrderTrackingEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| Vendor › Orders › detail and printed invoice figures:
| RQA-VEN-006 a line "1 × $132.00" whose total $732.00 includes $600.00 fixed shipping
|   lists the fixed shipping, and the subtotal says it includes fixed shipping;
| RQA-VEN-010 "Delivered" (order status + shipment status at the same minute) is listed once;
| RQA-VEN-014 the estimate keeps both months ("29 Nov–2 Dec") and "Subtotal (1 item)".
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->owner = $this->createVendorOwner($this->shop->tenant);
    $this->order = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->catalog->id => 1], 'order-key-fig', 'tenant');
    $this->shop->tenant->run(function (): void {
        $order = Order::query()->with('items')->where('uuid', $this->order->uuid)->sole();
        $order->items->first()->forceFill(['quantity' => 1, 'price' => 13200, 'sub_total' => 13200, 'fixed_shipping' => 60000, 'discount' => 0, 'tax' => 0, 'shipping_fee' => 0, 'grand_total' => 73200])->save();
        $order->forceFill(['display_subtotal' => 73200, 'delivery_min_date' => Carbon::parse('2026-11-29'), 'delivery_max_date' => Carbon::parse('2026-12-02')])->save();

        $at = Carbon::parse('2026-11-27 19:16:20');
        OrderTrackingEvent::query()->create(['order_id' => $order->id, 'status' => FulfilmentStatus::Delivered, 'actor_type' => ActorType::System])->forceFill(['created_at' => $at])->save();
        OrderStatusHistory::query()->create(['order_id' => $order->id, 'from_status' => OrderStatus::Shipped, 'to_status' => OrderStatus::Delivered, 'actor_type' => ActorType::System])->forceFill(['created_at' => $at->copy()->addSeconds(5)])->save();
    });
    $this->url = fn (string $path = ''): string => $this->tenantUrl($this->shop->tenant, 'admin/sales/orders/'.$this->order->uuid.$path);
    $this->actingAsVendor($this->owner, $this->shop->tenant);
});

it('shows the fixed shipping inside the order line so the line adds up', function (): void {
    $html = $this->get(($this->url)())->assertOk()
        ->assertSee('1 × $132.00')
        ->assertSee('data-line-part="fixed_shipping"', false)
        ->assertSee(__('commerce::orders_admin.line_parts.fixed_shipping'))
        ->assertSee('+ $600.00')
        ->assertSee('$732.00')
        ->assertSee(trans_choice('commerce::orders_admin.totals.subtotal_with_fixed', 1, ['count' => 1]))
        ->getContent();

    expect($html)->toContain('Subtotal (1 item, incl. fixed shipping)');
});

it('prints the same line breakdown on the invoice', function (): void {
    $this->get(($this->url)('/invoice'))->assertOk()
        ->assertSee('1 × $132.00')
        ->assertSee('+ $600.00')
        ->assertSee('$732.00');
});

it('lists a status once per minute in the shipping timeline', function (): void {
    $html = $this->get(($this->url)())->assertOk()->getContent();

    expect(substr_count($html, '<p class="text-sm font-medium text-ink">'.FulfilmentStatus::Delivered->label().'</p>'))->toBe(1);
});

it('keeps both months in the estimated delivery window', function (): void {
    $this->get(($this->url)())->assertOk()->assertSee('29 Nov–2 Dec')->assertDontSee('29–2 Dec');
});

it('pluralises the subtotal item count', function (): void {
    expect(trans_choice('commerce::orders_admin.totals.subtotal', 1, ['count' => 1]))->toBe('Subtotal (1 item)')
        ->and(trans_choice('commerce::orders_admin.totals.subtotal', 3, ['count' => 3]))->toBe('Subtotal (3 items)')
        ->and(trans_choice('commerce::orders_admin.totals.subtotal', 1, ['count' => 1], 'ar'))->toBe('المجموع الفرعي (منتج واحد)')
        ->and(trans_choice('commerce::orders_admin.totals.subtotal', 5, ['count' => 5], 'ar'))->toBe('المجموع الفرعي (5 منتجات)');
});
