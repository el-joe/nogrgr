<?php

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\DTOs\CreateReturnData;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\ReturnRequestService;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| VEN-020 / 021 / 021a–d / 022 / 022a / 023: the store's orders, cancel, manual refund
| (store-collected only), own-products shipping status, pay platform (settlement),
| returns with the store's transitions and refunds — current store only.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->owner = $this->createVendorOwner($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'order-key-v1', 'tenant');
    $this->url = fn (string $path = '') => $this->tenantUrl($this->shop->tenant, 'admin/sales'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->shop->tenant);
});

it('sends guests to the login and forbids staff without permissions', function (): void {
    $this->get(($this->url)('/orders'))->assertRedirect($this->tenantUrl($this->shop->tenant, 'login'));

    $staff = $this->shop->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view']);

        return $staff;
    });
    $this->actingAsVendor($staff, $this->shop->tenant);

    foreach (['/orders', '/orders/data', '/returns', '/refunds', "/orders/{$this->order->uuid}"] as $path) {
        $this->getJson(($this->url)($path))->assertForbidden();
    }

    $this->postJson(($this->url)("/orders/{$this->order->uuid}/cancel"), ['reason' => 'out_of_stock'])->assertForbidden();
    $this->getJson($this->tenantUrl($this->shop->tenant, 'admin/settings/payment-gateways'))->assertForbidden();
});

it('lists the store orders with KPIs, filters, export and the detail with settlement figures', function (): void {
    ($this->as)();

    $this->get(($this->url)('/orders'))->assertOk()->assertSee(__('commerce::vendor_orders.kpi.collected'))->assertSee('$63.80');
    $data = fn (string $query = ''): array => $this->getJson(($this->url)('/orders/data?draw=1&start=0&length=10'.$query))->assertOk()->json();

    expect($data()['recordsFiltered'])->toBe(1)
        ->and($data()['data'][0]['commission'])->toContain('$9.60')
        ->and($data('&status=shipped')['recordsFiltered'])->toBe(0)
        ->and($data('&search='.$this->order->number)['recordsFiltered'])->toBe(1);

    expect($this->get(($this->url)('/orders/export'))->assertOk()->streamedContent())->toContain($this->order->number);

    $this->get(($this->url)("/orders/{$this->order->uuid}"))
        ->assertOk()
        ->assertSee($this->order->number)
        ->assertSee('$54.20')
        ->assertSee(__('commerce::vendor_orders.central_note'))
        ->assertSee(__('commerce::settlement.pay', ['amount' => '$54.20']));
    $this->get(($this->url)("/orders/{$this->order->uuid}/invoice"))->assertOk()->assertSee('data-autoprint', false);
    $this->getJson(($this->url)('/orders/00000000-0000-0000-0000-000000000000'))->assertNotFound();
});

it('cancels with a reason code and refunds the store-collected payment', function (): void {
    Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_v1', 'status' => 'succeeded'])]);
    ($this->as)();

    $this->get(($this->url)("/orders/{$this->order->uuid}/cancel"), xhrHeaders())->assertOk();
    $this->postJson(($this->url)("/orders/{$this->order->uuid}/cancel"), ['reason' => 'nope'])->assertJsonValidationErrors('reason');
    $this->postJson(($this->url)("/orders/{$this->order->uuid}/cancel"), ['reason' => 'out_of_stock', 'note' => 'Colour sold out'])->assertOk();

    expect(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Refunded)
        ->and(Refund::withoutTenantScope()->sole()->amount)->toBe(6380)
        ->and(OrderFact::withoutTenantScope()->where('order_uuid', $this->order->uuid)->value('status'))->toBe('refunded');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.com/v1/refunds');

    // QA-P5-010: the list shows the same (void) store share as the detail page.
    $row = $this->getJson(($this->url)('/orders/data?draw=1&start=0&length=10&status=refunded'))->assertOk()->json('data.0');
    expect($row['commission'])->toContain('$0.00');
});

it('refunds manually only money the store collected', function (): void {
    Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_v2', 'status' => 'succeeded'])]);
    ($this->as)();

    $this->get(($this->url)("/orders/{$this->order->uuid}/refund"), xhrHeaders())->assertOk()->assertSee('$63.80');
    $this->postJson(($this->url)("/orders/{$this->order->uuid}/refund"), ['amount' => '70', 'reason' => 'Missing item'])->assertUnprocessable();
    $this->postJson(($this->url)("/orders/{$this->order->uuid}/refund"), ['amount' => '19.00', 'reason' => 'Missing item'])->assertCreated();

    expect(Refund::withoutTenantScope()->sole()->status)->toBe(RefundStatus::Completed);

    // QA-P5-010: "Collected" and the list share account for the refund (63.80 − 19.00; 9.60 − 19.00).
    $this->get(($this->url)('/orders'))->assertOk()->assertSee('$44.80');
    expect($this->getJson(($this->url)('/orders/data?draw=1&start=0&length=10'))->json('data.0.commission'))->toContain('-$9.40');

    $platform = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->catalog->id => 1], 'order-key-v2', 'central');
    ($this->as)();
    $this->postJson(($this->url)("/orders/{$platform->uuid}/refund"), ['amount' => '1.00', 'reason' => 'Missing item'])->assertForbidden();

    $this->get(($this->url)('/refunds'))->assertOk();
    expect($this->getJson(($this->url)('/refunds/data?draw=1&start=0&length=10'))->json('recordsFiltered'))->toBe(1);
});

it('updates the shipping status of own-products orders only', function (): void {
    $company = ShippingCompany::factory()->create(['name' => 'Aramex', 'is_active' => true]);
    $own = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->own->id => 1], 'order-key-own', 'tenant');
    ($this->as)();

    $this->putJson(($this->url)("/orders/{$this->order->uuid}/shipping"), ['status' => 'packed'])->assertForbidden();
    $this->get(($this->url)("/orders/{$own->uuid}/shipping"), xhrHeaders())->assertOk();
    $this->putJson(($this->url)("/orders/{$own->uuid}/shipping"), ['status' => 'in_transit', 'shipping_company_id' => $company->id, 'tracking_number' => 'ARX 1'])->assertOk();
    $this->putJson(($this->url)("/orders/{$own->uuid}/shipping"), ['status' => 'delivered'])->assertOk();

    $fresh = orderIn($this->shop, $own);

    expect($fresh->status)->toBe(OrderStatus::Delivered)
        ->and($fresh->fulfilment_status)->toBe(FulfilmentStatus::Delivered)
        ->and($fresh->shipping_company_name)->toBe('Aramex')
        ->and(OrderFact::withoutTenantScope()->where('order_uuid', $own->uuid)->value('fulfilment_status'))->toBe('delivered');
});

it('pays the platform share of a store-collected order through the hosted checkout', function (): void {
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => function (HttpRequest $request) {
        return $request->method() === 'POST'
            ? Http::response(['id' => 'cs_settle', 'url' => 'https://checkout.stripe.com/c/pay/cs_settle'])
            : Http::response(['id' => 'cs_settle', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_settle', 'amount_total' => 5420, 'currency' => 'usd']);
    }]);
    ($this->as)();

    $this->get($this->tenantUrl($this->shop->tenant, "admin/finance/settle/{$this->order->uuid}"))->assertOk()->assertSee('$54.20')->assertSee('$5.00')->assertSee('$49.20');
    $this->post($this->tenantUrl($this->shop->tenant, "admin/finance/settle/{$this->order->uuid}"), ['gateway' => 'bogus'])->assertSessionHasErrors('gateway');
    $this->post($this->tenantUrl($this->shop->tenant, "admin/finance/settle/{$this->order->uuid}"), ['gateway' => 'stripe'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_settle');

    $settlement = OrderSettlement::withoutTenantScope()->sole();
    $log = PaymentLog::query()->withoutGlobalScopes()->findOrFail($settlement->payment_log_id);

    expect($settlement->amount)->toBe(5420)->and($settlement->shipping_cost)->toBe(500)->and($settlement->product_cost)->toBe(4920);

    $this->get($this->tenantUrl($this->shop->tenant, "admin/finance/settle/payments/{$log->id}/result"))->assertOk()->assertSee(__('commerce::settlement.result.paid_title'));

    expect($log->refresh()->status)->toBe(PaymentLogStatus::Captured)
        ->and($settlement->refresh()->status)->toBe(SettlementStatus::Paid);

    $this->get(($this->url)("/orders/{$this->order->uuid}"))->assertDontSee(__('commerce::settlement.pay', ['amount' => '$54.20']));
    $this->get($this->tenantUrl($this->shop->tenant, 'admin/finance/settle/payments/999999/result'))->assertNotFound();
});

it('lets the store review returns but not inspect platform-shipped items', function (): void {
    $this->shop->tenant->run(function (): void {
        $order = Order::query()->where('uuid', $this->order->uuid)->sole();
        $lifecycle = app(OrderLifecycleService::class);
        $lifecycle->transition($order, OrderStatus::Processing);
        $lifecycle->transition($order, OrderStatus::Shipped);
        $lifecycle->transition($order, OrderStatus::Delivered);
    });
    $order = orderIn($this->shop, $this->order);
    $return = app(ReturnRequestService::class)->create($this->shop->tenant->id, $order, new CreateReturnData($order->items->first()->id, 1, 'not_as_described', 'The colour is different.'), Actor::system());
    $other = shopSetup('other');
    ($this->as)();

    $this->get(($this->url)('/returns'))->assertOk();
    expect($this->getJson(($this->url)('/returns/data?draw=1&start=0&length=10'))->json('recordsFiltered'))->toBe(1);
    $this->get(($this->url)("/returns/{$return->id}"))->assertOk()->assertSee($return->reference)->assertSee(ReturnAction::Approve->label());

    $this->postJson(($this->url)("/returns/{$return->id}/actions/approve"), ['note' => 'OK'])->assertOk();
    $this->postJson(($this->url)("/returns/{$return->id}/actions/mark_received"))->assertUnprocessable();
    $this->postJson(($this->url)("/returns/{$return->id}/notes"), ['note' => 'Please keep the box.', 'customer_visible' => 1])->assertOk();

    expect($return->refresh()->status)->toBe(ReturnStatus::Approved);

    $foreign = ReturnRequest::withoutTenantScope()->create($return->only(['order_uuid', 'order_number', 'order_item_id', 'product_name', 'quantity', 'type', 'reason_code', 'description', 'status', 'created_by_type']) + ['reference' => 'RMA-X', 'tenant_id' => $other->tenant->id]);
    $this->getJson(($this->url)("/returns/{$foreign->id}"))->assertNotFound();
});
