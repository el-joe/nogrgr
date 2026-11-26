<?php

use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\Billing\DTOs\Ledger\LedgerLine;
use App\Modules\Billing\DTOs\Ledger\LedgerPostingData;
use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Models\LedgerEntry;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Services\Ledger\FinancePostings;
use App\Modules\Billing\Services\Ledger\LedgerService;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| QA-P5-011: a return refund on a platform-collected order that is larger than the vendor
| share leaves the store owing the rest (OrderSettlement, decision #72 h).
| Golden order (2 × central in SA, collected by the platform): grand 63.80, platform 54.20,
| vendor 9.60. Return of 1 unit (RefundCalculator::forReturn) = 29.40 →
|   vendor share 9.60 − 29.40 = −19.80 → vendor_payable 0, vendor_receivable 19.80.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->tenantId = (int) $this->shop->tenant->id;
    $this->order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'return-ledger-1', 'central');
    $this->fact = factId($this->order);
    $this->balance = fn (): array => app(LedgerService::class)->balanceOf($this->tenantId);
    $branch = Branch::factory()->create(['is_active' => true]);
    BranchStock::query()->create(['branch_id' => $branch->id, 'product_id' => $this->shop->central->id, 'variant_key' => 0, 'on_hand' => 10, 'reserved' => 0]);

    $this->actingAs(commerceAdmin(), 'admin');
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'in_transit', 'tracking_number' => 'AWB1', 'branch_id' => $branch->id])->assertOk();
    $this->putJson($this->centralUrl("/admin/shipping-queue/{$this->fact}/status"), ['status' => 'delivered'])->assertOk();

    $this->refundOneUnit = function (): Refund {
        fakeStripeRefund();
        $item = orderIn($this->shop, $this->order)->items->first();
        $this->postJson($this->centralUrl("/admin/orders/{$this->fact}/return"), ['order_item_id' => $item->id, 'quantity' => 1, 'reason_code' => 'defective', 'description' => 'Arrived with a cracked screen.'])->assertCreated();
        $return = ReturnRequest::withoutTenantScope()->sole();

        foreach (['approve' => [], 'mark_received' => [], 'inspect' => ['result' => 'accepted'], 'refund' => ['amount' => '29.40']] as $action => $data) {
            $this->postJson($this->centralUrl("/admin/returns/{$return->id}/actions/{$action}"), $data)->assertOk();
        }

        return Refund::withoutTenantScope()->sole();
    };
});

it('records the part of a return refund the store bears beyond its vendor share', function (): void {
    expect(($this->balance)())->toBe(['payable' => 960, 'receivable' => 0, 'net' => 960]);

    $refund = ($this->refundOneUnit)();
    expect($refund->amount)->toBe(2940);

    $posting = LedgerTransaction::withoutTenantScope()->where('idempotency_key', 'refund:'.$refund->id)->sole();
    $figures = app(OrderFinanceService::class)->figures(orderIn($this->shop, $this->order), $this->tenantId);

    expect(($this->balance)())->toBe(['payable' => 0, 'receivable' => 1980, 'net' => -1980])
        ->and($figures->vendorShare->minor)->toBe(-1980)
        ->and($figures->owedToPlatform->minor)->toBe(1980)
        ->and($posting->source)->toBe(LedgerSource::Refund)
        ->and($posting->net_amount)->toBe(-2940)
        ->and($posting->entries->where('account', LedgerAccount::VendorPayable)->sum('debit'))->toBe(960)
        ->and($posting->entries->where('account', LedgerAccount::VendorReceivable)->sum('debit'))->toBe(1980);

    // A replayed refund event and a back-fill run change nothing.
    RefundCompleted::dispatch($refund);
    $this->artisan('finance:backfill-ledger', ['--tenant' => [$this->tenantId]])->expectsOutputToContain('Posted 0 transaction(s)')->assertSuccessful();

    expect(LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->count())->toBe(2)
        ->and(($this->balance)())->toBe(['payable' => 0, 'receivable' => 1980, 'net' => -1980]);
});

it('corrects a refund posted by an earlier release that only reversed the vendor share', function (): void {
    $refund = ($this->refundOneUnit)();

    // What the earlier release wrote: the refund posting reversed the 9.60 payable only.
    LedgerEntry::withoutTenantScope()->where('tenant_id', $this->tenantId)->whereHas('transaction', fn ($q) => $q->where('idempotency_key', 'refund:'.$refund->id))->delete();
    LedgerTransaction::withoutTenantScope()->where('idempotency_key', 'refund:'.$refund->id)->delete();
    app(LedgerService::class)->post(new LedgerPostingData(
        tenantId: $this->tenantId,
        source: LedgerSource::Refund,
        idempotencyKey: 'refund:'.$refund->id,
        description: 'Refund',
        lines: [
            LedgerLine::change(LedgerAccount::VendorPayable, -960, $this->order->uuid, $this->order->number),
            LedgerLine::change(LedgerAccount::PlatformCash, -960, $this->order->uuid, $this->order->number),
        ],
        orderUuid: $this->order->uuid,
    ));
    expect(($this->balance)())->toBe(['payable' => 0, 'receivable' => 0, 'net' => 0]);

    $this->artisan('finance:backfill-ledger', ['--dry-run' => true, '--tenant' => [$this->tenantId]])->expectsOutputToContain('Would post 1 transaction(s)')->assertSuccessful();
    expect(($this->balance)()['receivable'])->toBe(0);

    $this->artisan('finance:backfill-ledger', ['--tenant' => [$this->tenantId]])->expectsOutputToContain('Posted 1 transaction(s)')->assertSuccessful();

    $correction = LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->latest('id')->first();
    expect(($this->balance)())->toBe(['payable' => 0, 'receivable' => 1980, 'net' => -1980])
        ->and($correction->idempotency_key)->toStartWith(FinancePostings::orderKey($this->tenantId, $this->order->uuid, 'adjust:'))
        ->and($correction->meta['key'])->toBe('order_adjustment')
        ->and($correction->net_amount)->toBe(-1980);

    // Idempotent: nothing left to correct.
    $this->artisan('finance:backfill-ledger', ['--tenant' => [$this->tenantId]])->expectsOutputToContain('Posted 0 transaction(s)')->assertSuccessful();
    expect(app(FinancePostings::class)->reconcile($this->tenantId, orderIn($this->shop, $this->order)))->toBeNull()
        ->and(($this->balance)()['receivable'])->toBe(1980);
});
