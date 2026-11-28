<?php

use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| What the order pages say after a cancellation or a part refund: no "To ship" on a
| cancelled shipment, "Partially refunded" instead of "Paid", no checkout percentages next
| to shares a refund has changed. Golden order: total 63.80, platform 54.20, store 9.60.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->owner = $this->createVendorOwner($this->shop->tenant);
    $this->order = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->catalog->id => 2], 'order-key-state', 'tenant');
    $this->fact = factId($this->order);
    $this->vendorPage = fn (): string => $this->actingAsVendor($this->owner, $this->shop->tenant)
        ->get($this->tenantUrl($this->shop->tenant, 'admin/sales/orders/'.$this->order->uuid))->assertOk()->getContent();
});

it('does not show a cancelled shipment as waiting to ship', function (): void {
    $this->shop->tenant->run(fn () => Order::query()->where('uuid', $this->order->uuid)->update(['status' => OrderStatus::Cancelled]));

    expect(($this->vendorPage)())
        ->toContain(e(__('commerce::fulfilment.not_shipping')))
        ->not->toContain('>'.FulfilmentStatus::Pending->label().'<');
});

it('shows a part refund as partially refunded, without the checkout percentages', function (): void {
    Refund::withoutTenantScope()->create([
        'reference' => 'RF-T'.random_int(10000, 99999), 'tenant_id' => (int) $this->shop->tenant->id, 'order_uuid' => $this->order->uuid,
        'order_number' => $this->order->number, 'source' => RefundSource::Manual, 'reason' => 'Test', 'currency' => 'USD', 'amount' => 1000,
        'items_amount' => 1000, 'method' => RefundMethod::OriginalPayment, 'gateway' => 'stripe', 'payment_source' => 'tenant',
        'status' => RefundStatus::Completed, 'requested_by_type' => ActorType::Admin, 'requested_at' => now(),
    ]);
    $label = __('commerce::orders_admin.partially_refunded');

    expect(($this->vendorPage)())
        ->toContain($label)
        ->toContain(__('commerce::settlement.vendor_share_plain'))
        ->not->toContain(__('commerce::settlement.vendor_share', ['percent' => 15]));

    $this->actingAs(commerceAdmin(), 'admin');
    $row = $this->getJson($this->centralUrl('/admin/orders/data?draw=1&start=0&length=10'))->assertOk()->json('data.0.payment');

    expect($row)->toContain($label)->toContain('Stripe');
    $this->get($this->centralUrl("/admin/orders/{$this->fact}"))->assertOk()->assertSee($label)->assertDontSee('data-width="85"', false);
});
