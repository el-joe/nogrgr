<?php

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Models\OrderAttachment;
use App\Modules\Commerce\Models\StockReservation;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Shipping\Models\ShippingCompany;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| ADM-020 / 021 / 022 / 022a: admin orders from order_facts, detail from the store DB,
| cancel (+ cancellation refund through Stripe), reassign carrier, notes, shipping queue,
| status steps (branch stock deducted on shipping), attachments on the central disk.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->fact = factId($this->order);
    $this->company = ShippingCompany::factory()->create(['name' => 'SMSA Express', 'is_active' => true]);
});

it('protects the order pages', function (): void {
    $this->get($this->centralUrl('/admin/orders'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->getJson($this->centralUrl("/admin/orders/{$this->fact}"))->assertUnauthorized();

    $this->actingAs(commerceAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/orders'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/orders/data'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/shipping-queue'))->assertForbidden();

    $this->actingAs(commerceAdmin(['orders.view']), 'admin');
    $this->get($this->centralUrl('/admin/orders'))->assertOk();
    $this->getJson($this->centralUrl('/admin/orders/export'))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/cancel"), ['reason' => 'out_of_stock'])->assertForbidden();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/notes"), ['note' => 'x'])->assertForbidden();
    $this->getJson($this->centralUrl('/admin/orders/999999'))->assertNotFound();
});

it('lists every store order from order_facts with filters and exports them', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $data = fn (string $query = ''): array => $this->getJson($this->centralUrl('/admin/orders/data?draw=1&start=0&length=10'.$query))->assertOk()->json();

    expect($data()['recordsFiltered'])->toBe(1)
        ->and($data()['data'][0]['order'])->toContain($this->order->number)
        ->and($data('&status=shipped')['recordsFiltered'])->toBe(0)
        ->and($data('&payment=paid')['recordsFiltered'])->toBe(1)
        ->and($data('&tenant_id='.$this->shop->tenant->id)['recordsFiltered'])->toBe(1)
        ->and($data('&search=nobody-matches')['recordsFiltered'])->toBe(0);

    $csv = $this->get($this->centralUrl('/admin/orders/export'))->assertOk()->streamedContent();
    expect($csv)->toContain($this->order->number)->toContain('63.80');
});

it('shows the order detail read from the store database', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl("/admin/orders/{$this->fact}"))
        ->assertOk()
        ->assertSee($this->order->number)
        ->assertSee('$63.80')
        ->assertSee('$54.20')
        ->assertSee('pi_paid_1')
        ->assertSee(__('commerce::orders_admin.cancel.button'));

    $this->get($this->centralUrl("/admin/orders/{$this->fact}/receipt"))->assertOk()->assertSee('data-autoprint', false);
});

it('cancels a paid order in 2 steps and refunds it in full through Stripe', function (): void {
    fakeStripeRefund();
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl("/admin/orders/{$this->fact}/cancel"), xhrHeaders())->assertOk()->assertSee('$63.80')->assertSee('x-data', false);
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/cancel"), ['reason' => 'changed_mind'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/cancel"), ['reason' => 'other'])->assertJsonValidationErrors('note');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/cancel"), ['reason' => 'out_of_stock', 'note' => 'Colour sold out'])->assertOk();

    $order = orderIn($this->shop, $this->order);
    $fact = OrderFact::withoutTenantScope()->find($this->fact);

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->cancel_reason_code?->value)->toBe('out_of_stock')
        ->and($fact->status)->toBe('refunded')
        ->and(StockReservation::withoutTenantScope()->where('order_uuid', $this->order->uuid)->pluck('status')->unique()->all())->toBe([StockReservationStatus::Released]);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.com/v1/refunds' && $request['payment_intent'] === 'pi_paid_1' && (int) $request['amount'] === 6380);

    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/cancel"), ['reason' => 'out_of_stock'])->assertUnprocessable();
});

it('reassigns the carrier with a new delivery estimate and keeps notes', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl("/admin/orders/{$this->fact}/company"), xhrHeaders())->assertOk()->assertSee('SMSA Express');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/company/estimate"), ['shipping_company_id' => $this->company->id])->assertOk()->assertJsonPath('success', true);
    $this->putJson($this->centralUrl("/admin/orders/{$this->fact}/company"), ['shipping_company_id' => 999999])->assertJsonValidationErrors('shipping_company_id');
    $this->putJson($this->centralUrl("/admin/orders/{$this->fact}/company"), ['shipping_company_id' => $this->company->id])->assertOk();

    expect(orderIn($this->shop, $this->order)->shipping_company_name)->toBe('SMSA Express')
        ->and(orderIn($this->shop, $this->order)->delivery_min_date)->not->toBeNull()
        ->and(OrderFact::withoutTenantScope()->find($this->fact)->shipping_company_name)->toBe('SMSA Express');

    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/notes"), ['note' => ''])->assertJsonValidationErrors('note');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/notes"), ['note' => 'Called the customer'])->assertOk();
    $this->get($this->centralUrl("/admin/orders/{$this->fact}"))->assertSee('Called the customer');
});

it('ships from the queue, deducting branch stock of the paid central lines', function (): void {
    $branch = Branch::factory()->create(['is_active' => true]);
    BranchStock::query()->create(['branch_id' => $branch->id, 'product_id' => $this->shop->central->id, 'variant_key' => 0, 'on_hand' => 10, 'reserved' => 0]);
    $this->actingAs(commerceAdmin(['shipping-queue.view', 'shipping-queue.manage']), 'admin');

    $queue = fn (string $tab): array => $this->getJson($this->centralUrl('/admin/shipping-queue/data?draw=1&start=0&length=10&tab='.$tab))->assertOk()->json();
    expect($queue('to_ship')['recordsFiltered'])->toBe(1)->and($queue('in_transit')['recordsFiltered'])->toBe(0);

    $this->get($this->centralUrl('/admin/shipping-queue'))->assertOk();
    $this->get($this->centralUrl("/admin/shipping-queue/{$this->fact}"))->assertOk()->assertSee($this->order->number);
    $this->get($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), xhrHeaders())->assertOk();
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'in_transit'])->assertJsonValidationErrors('tracking_number');
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'delivered', 'tracking_number' => 'X'])->assertUnprocessable();
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'in_transit', 'tracking_number' => 'SMSA 2900 1182', 'shipping_company_id' => $this->company->id, 'branch_id' => $branch->id, 'location' => 'Riyadh WH'])->assertOk();

    $order = orderIn($this->shop, $this->order);

    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::InTransit)
        ->and($order->fulfilment_branch_id)->toBe($branch->id)
        ->and($order->tracking_number)->toBe('SMSA 2900 1182')
        ->and($order->trackingEvents)->toHaveCount(1)
        ->and(BranchStock::query()->where('branch_id', $branch->id)->value('on_hand'))->toBe(8)
        ->and($this->shop->central->fresh()->stock)->toBe(8)
        ->and(StockReservation::withoutTenantScope()->where('order_uuid', $this->order->uuid)->value('status'))->toBe(StockReservationStatus::Deducted)
        ->and(OrderFact::withoutTenantScope()->find($this->fact)->fulfilment_status)->toBe('in_transit')
        ->and($queue('in_transit')['recordsFiltered'])->toBe(1);

    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'out_for_delivery'])->assertOk();
    expect(OrderFact::withoutTenantScope()->find($this->fact)->fulfilment_status)->toBe('out_for_delivery');

    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'delivered'])->assertOk();
    expect(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Delivered)
        ->and(OrderFact::withoutTenantScope()->find($this->fact)->status)->toBe('delivered')
        ->and(BranchStock::query()->where('branch_id', $branch->id)->value('on_hand'))->toBe(8);
});

it('marks selected orders shipped in bulk', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/shipping-queue/mark-shipped'), ['ids' => []])->assertJsonValidationErrors('ids');
    $this->postJson($this->centralUrl('/admin/shipping-queue/mark-shipped'), ['ids' => [$this->fact, 999999]])->assertOk()->assertJsonPath('data.done', 1);

    expect(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Shipped);
});

it('stores shipment attachments on the private central disk', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');

    $this->postJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/attachments"), ['kind' => 'shipping_label', 'file' => UploadedFile::fake()->create('virus.exe', 10)])->assertJsonValidationErrors('file');
    $this->post($this->centralUrl("/admin/shipping-queue/{$this->fact}/attachments"), ['kind' => 'shipping_label', 'file' => UploadedFile::fake()->create('label.pdf', 84, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();

    $file = OrderAttachment::withoutTenantScope()->sole();
    Storage::disk('central')->assertExists($file->path);

    $this->get($this->centralUrl("/admin/shipping-queue/{$this->fact}/attachments/{$file->id}"))->assertOk()->assertDownload('label.pdf');
    $this->get($this->centralUrl("/admin/shipping-queue/{$this->fact}"))->assertSee('label.pdf');
    $this->deleteJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/attachments/{$file->id}"))->assertOk();

    Storage::disk('central')->assertMissing($file->path);
    expect(OrderAttachment::withoutTenantScope()->count())->toBe(0);
});

it('keeps own-products orders read-only for the platform', function (): void {
    $own = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->own->id => 1], 'order-key-own');
    $fact = factId($own);
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl("/admin/orders/{$fact}"))->assertOk()->assertSee(__('commerce::orders_admin.own_read_only'))->assertDontSee(__('commerce::orders_admin.cancel.button'));
    $this->postJson($this->centralUrl("/admin/orders/{$fact}/cancel"), ['reason' => 'out_of_stock'])->assertForbidden();
    $this->getJson($this->centralUrl("/admin/shipping-queue/{$fact}"))->assertForbidden();
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$fact}/status"), ['status' => 'packed'])->assertForbidden();

    expect($this->getJson($this->centralUrl('/admin/shipping-queue/data?draw=1&start=0&length=10'))->json('recordsFiltered'))->toBe(1);
});
