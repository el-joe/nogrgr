<?php

use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| RQA-VEN-009: an order whose completed refunds (here through a return) cover the whole
| amount charged reads payment "Refunded" with refunded_at; a partial refund keeps "Paid".
| Golden order: 2 × central (line grand 58.80), shipping 5.00, total 63.80.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->order = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->catalog->id => 2]);
    $this->fact = factId($this->order);
    $branch = Branch::factory()->create(['is_active' => true]);
    BranchStock::query()->create(['branch_id' => $branch->id, 'product_id' => $this->shop->central->id, 'variant_key' => 0, 'on_hand' => 10, 'reserved' => 0]);
    $this->actingAs(commerceAdmin(), 'admin');
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'in_transit', 'tracking_number' => 'AWB1', 'branch_id' => $branch->id])->assertOk();
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'delivered'])->assertOk();
    fakeStripeRefund();
    $this->returnAndRefund = function (int $quantity, string $amount): void {
        $item = orderIn($this->shop, $this->order)->items->first();
        $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => $quantity, 'reason_code' => 'defective', 'description' => 'Arrived broken, please refund.'])->assertCreated();
        $return = ReturnRequest::withoutTenantScope()->latest('id')->first();

        foreach (['approve' => [], 'mark_received' => [], 'inspect' => ['result' => 'accepted'], 'refund' => ['amount' => $amount]] as $action => $data) {
            $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/{$action}"), $data)->assertOk();
        }
    };
});

it('marks the payment refunded when a return refunds the whole amount', function (): void {
    ($this->returnAndRefund)(2, '63.80');

    $order = orderIn($this->shop, $this->order);
    expect($order->status)->toBe(OrderStatus::Returned)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->refunded_at)->not->toBeNull();
});

it('keeps the payment paid after a partial refund', function (): void {
    ($this->returnAndRefund)(1, '29.40');

    $order = orderIn($this->shop, $this->order);
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->refunded_at)->toBeNull();
});
