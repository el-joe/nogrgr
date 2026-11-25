<?php

use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\RefundAttempt;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Modules\Billing\Events\AffiliateConversionReversalRequested;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Commerce\Notifications\OrderCustomerNotification;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Reporting\Models\OrderFact;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| ADM-021b / 021c / 023 / 023a / 024 / 090: refunds and returns of store orders from the
| admin panel. Golden order: 2 × central (line grand 58.80), shipping 5.00, total 63.80.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2]);
    $this->fact = factId($this->order);
    $this->branch = Branch::factory()->create(['is_active' => true]);
    BranchStock::query()->create(['branch_id' => $this->branch->id, 'product_id' => $this->shop->central->id, 'variant_key' => 0, 'on_hand' => 10, 'reserved' => 0]);
    $this->deliver = function (): void {
        $this->actingAs(commerceAdmin(), 'admin');
        $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'in_transit', 'tracking_number' => 'AWB1', 'branch_id' => $this->branch->id])->assertOk();
        $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'delivered'])->assertOk();
    };
});

it('protects the returns, refunds and policy pages', function (): void {
    foreach (['/admin/returns', '/admin/refunds', '/admin/storefront/return-policy'] as $path) {
        $this->get($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
    }

    $this->actingAs(commerceAdmin([]), 'admin');

    foreach (['/admin/returns', '/admin/returns/data', '/admin/refunds', '/admin/refunds/data', '/admin/storefront/return-policy'] as $path) {
        $this->getJson($this->centralUrl($path))->assertForbidden();
    }

    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => 1, 'reason' => 'Test'])->assertForbidden();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), [])->assertForbidden();

    $this->actingAs(commerceAdmin(['returns.view', 'refunds.view']), 'admin');
    $this->get($this->centralUrl('/admin/returns'))->assertOk();
    $this->get($this->centralUrl('/admin/refunds'))->assertOk();
    $this->postJson($this->centralUrl('/admin/refunds/1/action'), ['action' => 'complete'])->assertForbidden();
});

it('issues a manual refund capped at the refundable amount through Stripe', function (): void {
    fakeStripeRefund();
    Event::fake([AffiliateConversionReversalRequested::class]);
    $this->actingAs(commerceAdmin(), 'admin');

    $this->get($this->centralUrl("/admin/orders/{$this->fact}/refund"), xhrHeaders())->assertOk()->assertSee('$63.80');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => 'abc', 'reason' => ''])->assertJsonValidationErrors(['amount', 'reason']);
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '63.81', 'reason' => 'Damaged item'])->assertUnprocessable()->assertJsonPath('data.reason', 'amount_exceeds');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '40.00', 'reason' => 'Damaged item'])->assertCreated();

    $refund = Refund::withoutTenantScope()->sole();

    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($refund->amount)->toBe(4000)
        ->and($refund->source)->toBe(RefundSource::Manual)
        ->and($refund->gateway_refund_id)->toBe('re_test_1')
        ->and(RefundAttempt::query()->where('refund_id', $refund->id)->count())->toBe(1)
        ->and(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Paid);

    Http::assertSent(fn (HttpRequest $request): bool => $request['amount'] === '4000' || (int) $request['amount'] === 4000);
    Event::assertDispatched(AffiliateConversionReversalRequested::class, fn ($event): bool => $event->refundedAmountMinor === 4000 && $event->tenantId === $this->shop->tenant->id);
    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn ($n): bool => $n->eventName === 'refund_processed');

    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '23.81', 'reason' => 'Rest'])->assertUnprocessable();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '23.80', 'reason' => 'Rest'])->assertCreated();

    expect(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Refunded)
        ->and(OrderFact::withoutTenantScope()->find($this->fact)->status)->toBe('refunded');
});

it('retries, completes and rejects failed refunds with a payment log', function (): void {
    $failed = ['id' => 're_failed', 'status' => 'failed', 'failure_reason' => 'expired_or_canceled_card'];
    Http::fake(['https://api.stripe.com/v1/refunds' => Http::sequence()->push($failed)->push(['id' => 're_retry', 'status' => 'succeeded'])->push($failed)->push($failed)]);
    $this->actingAs(commerceAdmin(), 'admin');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '10.00', 'reason' => 'Late delivery'])->assertCreated();
    $failed = Refund::withoutTenantScope()->sole();

    expect($failed->status)->toBe(RefundStatus::Failed)->and($failed->failure_reason)->toBe('expired_or_canceled_card');
    Notification::assertSentTo(app(AdminRepositoryContract::class)->activeWithPermission('refunds.view'), OrderAlertNotification::class);

    $data = $this->getJson($this->centralUrl('/admin/refunds/data?draw=1&start=0&length=10&status=failed'))->assertOk()->json();
    expect($data['recordsFiltered'])->toBe(1)->and($data['data'][0]['actions'])->toContain('retry');

    $this->get($this->centralUrl("/admin/refunds/{$failed->id}"), xhrHeaders())->assertOk()->assertSee('expired_or_canceled_card');
    $this->get($this->centralUrl("/admin/refunds/{$failed->id}/retry"), xhrHeaders())->assertOk();

    $this->postJson($this->centralUrl("/admin/refunds/{$failed->id}/action"), ['action' => 'retry', 'note' => 'Settlement window passed'])->assertOk();

    expect($failed->refresh()->status)->toBe(RefundStatus::Completed)
        ->and($failed->attempts)->toBe(2)
        ->and($failed->attemptLog()->count())->toBe(2);

    $this->postJson($this->centralUrl("/admin/refunds/{$failed->id}/action"), ['action' => 'reject', 'note' => 'x'])->assertUnprocessable();

    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '5.00', 'reason' => 'Gift'])->assertCreated();
    $second = Refund::withoutTenantScope()->latest('id')->first();
    $this->postJson($this->centralUrl("/admin/refunds/{$second->id}/action"), ['action' => 'reject'])->assertJsonValidationErrors('note');
    $this->postJson($this->centralUrl("/admin/refunds/{$second->id}/action"), ['action' => 'reject', 'note' => 'Duplicate'])->assertOk();
    expect($second->refresh()->status)->toBe(RefundStatus::Rejected);

    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/refund"), ['amount' => '5.00', 'reason' => 'Cash back'])->assertCreated();
    $third = Refund::withoutTenantScope()->latest('id')->first();
    $this->postJson($this->centralUrl("/admin/refunds/{$third->id}/action"), ['action' => 'complete', 'note' => 'Paid by bank transfer'])->assertOk();
    expect($third->refresh()->status)->toBe(RefundStatus::Completed);

    $csv = $this->get($this->centralUrl('/admin/refunds/export'))->assertOk()->streamedContent();
    expect($csv)->toContain($third->reference);
});

it('runs a return through approve, receive, inspect + restock and refund', function (): void {
    ($this->deliver)();
    fakeStripeRefund();
    $item = orderIn($this->shop, $this->order)->items->first();

    expect(BranchStock::query()->where('branch_id', $this->branch->id)->value('on_hand'))->toBe(8);

    $this->get($this->centralUrl("/admin/orders/{$this->fact}/return"), xhrHeaders())->assertOk();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'changed_mind', 'description' => 'too short'])->assertJsonValidationErrors('description');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 3, 'reason_code' => 'changed_mind', 'description' => 'Ordered the wrong colour.'])->assertUnprocessable()->assertJsonPath('data.reason', 'quantity_exceeds');
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'changed_mind', 'description' => 'Ordered the wrong colour.'])->assertCreated();

    $return = ReturnRequest::withoutTenantScope()->sole();

    expect($return->status)->toBe(ReturnStatus::Pending)
        ->and($return->items_amount)->toBe(2940)
        ->and($return->shipping_amount)->toBe(0)
        ->and($return->refund_amount)->toBe(2940);

    $this->get($this->centralUrl('/admin/returns'))->assertOk();
    expect($this->getJson($this->centralUrl('/admin/returns/data?draw=1&start=0&length=10&tab=open'))->json('recordsFiltered'))->toBe(1);
    $this->get($this->centralUrl("/admin/returns/{$return->id}"))->assertOk()->assertSee($return->reference)->assertSee('$29.40');

    $act = fn (string $action, array $data = []) => $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/{$action}"), $data);

    $act('refund', ['amount' => '1.00'])->assertUnprocessable();
    $act('reject')->assertJsonValidationErrors('note');
    $this->get($this->centralUrl("/admin/returns/{$return->id}/actions/approve"), xhrHeaders())->assertOk();
    $act('approve', ['note' => 'Courier pickup tomorrow'])->assertOk();
    $act('mark_received')->assertOk();
    $act('inspect', [])->assertJsonValidationErrors('result');
    $act('inspect', ['result' => 'accepted', 'condition' => 'Opened — fully working', 'restock' => 1, 'branch_id' => $this->branch->id, 'then_refund' => 1, 'amount' => '29.40'])->assertOk();

    $return->refresh();
    $refund = Refund::withoutTenantScope()->sole();

    expect($return->status)->toBe(ReturnStatus::Refunded)
        ->and($return->restocked)->toBeTrue()
        ->and($refund->source)->toBe(RefundSource::Return)
        ->and($refund->amount)->toBe(2940)
        ->and($refund->status)->toBe(RefundStatus::Completed)
        ->and(BranchStock::query()->where('branch_id', $this->branch->id)->value('on_hand'))->toBe(9)
        ->and($return->events()->where('type', 'status')->pluck('to_status')->map->value->all())->toBe(['refunded', 'inspected', 'item_received', 'approved', 'pending']);

    Notification::assertSentTo($this->customer, OrderCustomerNotification::class, fn ($n): bool => $n->eventName === 'return_approved');

    $this->postJson($this->centralUrl("/admin/returns/{$return->id}/notes"), ['note' => 'Box opened, minor scratch.'])->assertOk();
    $act('close')->assertOk();
    expect($return->refresh()->status)->toBe(ReturnStatus::Closed);
    $act('approve')->assertUnprocessable();
});

it('returns the whole order for a seller-fault reason and marks the order returned', function (): void {
    ($this->deliver)();
    fakeStripeRefund();
    $item = orderIn($this->shop, $this->order)->items->first();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 2, 'reason_code' => 'defective', 'description' => 'Both arrived broken.'])->assertCreated();
    $return = ReturnRequest::withoutTenantScope()->sole();

    expect($return->items_amount)->toBe(5880)->and($return->shipping_amount)->toBe(500)->and($return->refund_amount)->toBe(6380);

    foreach (['approve' => [], 'mark_received' => [], 'inspect' => ['result' => 'accepted'], 'refund' => ['amount' => '63.80']] as $action => $data) {
        $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/{$action}"), $data)->assertOk();
    }

    expect($return->refresh()->status)->toBe(ReturnStatus::Refunded)
        ->and(orderIn($this->shop, $this->order)->status)->toBe(OrderStatus::Returned)
        ->and(OrderFact::withoutTenantScope()->find($this->fact)->status)->toBe('returned');

    $this->get($this->centralUrl("/admin/orders/{$this->fact}/return"), xhrHeaders())->assertForbidden();
});

it('rejects a failed inspection and keeps the money', function (): void {
    ($this->deliver)();
    $item = orderIn($this->shop, $this->order)->items->first();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'changed_mind', 'description' => 'Did not like it at all.'])->assertCreated();
    $return = ReturnRequest::withoutTenantScope()->sole();

    $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/request_info"), ['note' => 'Please send a photo.'])->assertOk();
    $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/resume"))->assertOk();
    $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/approve"))->assertOk();
    $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/mark_received"))->assertOk();
    $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/inspect"), ['result' => 'failed', 'note' => 'Used and scratched'])->assertOk();

    expect($return->refresh()->status)->toBe(ReturnStatus::Rejected)
        ->and(Refund::withoutTenantScope()->count())->toBe(0)
        ->and(BranchStock::query()->where('branch_id', $this->branch->id)->value('on_hand'))->toBe(8);
});

it('edits the platform return policy and its reasons', function (): void {
    $this->actingAs(commerceAdmin(['storefront.return-policy.manage']), 'admin');

    $this->get($this->centralUrl('/admin/storefront/return-policy'))->assertOk()->assertSee('Damaged on arrival');
    $this->putJson($this->centralUrl('/admin/storefront/return-policy'), ['window_days' => 400, 'restocking_fee' => 101, 'return_shipping_payer' => 'nobody'])->assertJsonValidationErrors(['window_days', 'restocking_fee', 'return_shipping_payer']);
    $this->putJson($this->centralUrl('/admin/storefront/return-policy'), ['window_days' => 30, 'restocking_fee' => '10', 'return_shipping_payer' => 'store', 'require_video' => 0, 'allow_exchanges' => 1])->assertOk();

    $policy = app(ReturnPolicyService::class)->policy();
    expect($policy->window_days)->toBe(30)->and($policy->restocking_fee_bps)->toBe(1000)->and($policy->require_video)->toBeFalse();

    $this->get($this->centralUrl('/admin/storefront/return-policy/reasons/create'), xhrHeaders())->assertOk();
    $this->postJson($this->centralUrl('/admin/storefront/return-policy/reasons'), ['name' => ['en' => '']])->assertJsonValidationErrors(['name.en', 'name.ar']);
    $this->postJson($this->centralUrl('/admin/storefront/return-policy/reasons'), ['name' => ['en' => 'Arrived late', 'ar' => 'وصل متأخرًا'], 'seller_fault' => 1, 'is_active' => 1])->assertCreated();

    $reason = ReturnReason::query()->where('code', 'arrived_late')->sole();
    expect($reason->seller_fault)->toBeTrue();

    $this->patchJson($this->centralUrl("/admin/storefront/return-policy/reasons/{$reason->id}/active"), ['is_active' => false])->assertOk();
    expect($reason->refresh()->is_active)->toBeFalse();
    $this->putJson($this->centralUrl("/admin/storefront/return-policy/reasons/{$reason->id}"), ['name' => ['en' => 'Arrived very late', 'ar' => 'وصل متأخرًا جدًا']])->assertOk();
    $this->deleteJson($this->centralUrl("/admin/storefront/return-policy/reasons/{$reason->id}"))->assertOk();
    expect(ReturnReason::query()->whereKey($reason->id)->exists())->toBeFalse();
});

it('never refunds one return twice: a failed or pending refund uses up the return value', function (): void {
    ($this->deliver)();
    fakeStripeRefund('failed');
    $item = orderIn($this->shop, $this->order)->items->first();
    $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'changed_mind', 'description' => 'Ordered the wrong colour.'])->assertCreated();
    $return = ReturnRequest::withoutTenantScope()->sole();
    $act = fn (string $action, array $data = []) => $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/{$action}"), $data);

    $act('approve')->assertOk();
    $act('mark_received')->assertOk();
    $act('inspect', ['result' => 'accepted', 'then_refund' => 1, 'amount' => '29.40'])->assertOk();

    $first = Refund::withoutTenantScope()->sole();
    expect($first->status)->toBe(RefundStatus::Failed)
        ->and($return->refresh()->status)->toBe(ReturnStatus::Inspected);

    // The failed refund is retryable: the return has nothing left to refund.
    $this->get($this->centralUrl("/admin/returns/{$return->id}"))->assertOk()->assertSee('$0.00');
    $act('refund', ['amount' => '29.40'])->assertUnprocessable()->assertJsonPath('data.reason', 'amount_exceeds');
    $act('refund', ['amount' => '0.01'])->assertUnprocessable();

    // Once the first refund is rejected, its value is free again.
    $this->postJson($this->centralUrl("/admin/refunds/{$first->id}/action"), ['action' => 'reject', 'note' => 'Card closed'])->assertOk();
    $act('refund', ['amount' => '29.40'])->assertOk();

    expect(Refund::withoutTenantScope()->where('return_request_id', $return->id)->count())->toBe(2)
        ->and(Refund::withoutTenantScope()->where('return_request_id', $return->id)->where('status', '!=', RefundStatus::Rejected->value)->sum('amount'))->toEqual(2940);
});
