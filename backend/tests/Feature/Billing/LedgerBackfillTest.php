<?php

use App\Modules\Billing\Models\LedgerEntry;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Services\Ledger\FinancePostings;
use App\Modules\Billing\Services\Ledger\LedgerService;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Models\OrderSettlement;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| finance:backfill-ledger (decision #74 d): paid orders / settlements / invoices that
| predate the ledger are posted with the live listeners' idempotency keys, so re-runs and
| later live events never post twice. Golden order (2 × central in SA): vendor 9.60
| (platform-collected) / platform 54.20 (store-collected).
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->tenantId = (int) $this->shop->tenant->id;
    $this->balance = fn (): array => app(LedgerService::class)->balanceOf($this->tenantId);
    // Orders paid "before the ledger": the live postings are wiped.
    $this->forgetLedger = function (): void {
        LedgerEntry::withoutTenantScope()->where('tenant_id', $this->tenantId)->delete();
        LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->delete();
    };
});

it('reports a dry run without writing, then posts once and is idempotent', function (): void {
    $platform = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'backfill-1', 'central');
    $store = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'backfill-2', 'tenant');
    ($this->forgetLedger)();

    $settlement = OrderSettlement::withoutTenantScope()->create([
        'reference' => 'ST-BF-'.random_int(1000, 9999), 'tenant_id' => $this->tenantId, 'order_uuid' => $store->uuid, 'order_number' => $store->number,
        'amount' => 2000, 'currency' => 'USD', 'gateway' => 'manual', 'method' => 'bank_transfer', 'status' => SettlementStatus::Paid, 'paid_at' => now()->subDay(),
    ]);

    $this->artisan('finance:backfill-ledger', ['--dry-run' => true, '--tenant' => [$this->tenantId]])
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('Would post 3 transaction(s) for 1 store(s).')
        ->assertSuccessful();
    expect(LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->count())->toBe(0);

    $this->artisan('finance:backfill-ledger', ['--tenant' => [$this->tenantId]])
        ->expectsOutputToContain('Posted 3 transaction(s) for 1 store(s).')
        ->assertSuccessful();

    // 9.60 owed to the store, 54.20 − 20.00 settled owed by the store.
    expect(($this->balance)())->toBe(['payable' => 960, 'receivable' => 3420, 'net' => 960 - 3420])
        ->and(LedgerTransaction::withoutTenantScope()->where('idempotency_key', FinancePostings::orderKey($this->tenantId, $platform->uuid))->exists())->toBeTrue()
        ->and(LedgerTransaction::withoutTenantScope()->where('idempotency_key', 'settlement:'.$settlement->id)->exists())->toBeTrue();

    // Back-filled postings keep the original payment time.
    $occurredAt = LedgerTransaction::withoutTenantScope()->where('idempotency_key', FinancePostings::orderKey($this->tenantId, $platform->uuid))->sole()->occurred_at;
    expect($occurredAt?->format('Y-m-d H:i'))->toBe($platform->paid_at?->format('Y-m-d H:i'));

    // Second run and a replayed live event post nothing.
    $this->artisan('finance:backfill-ledger')->expectsOutputToContain('Posted 0 transaction(s)')->assertSuccessful();
    $this->shop->tenant->run(fn () => OrderPaid::dispatch($platform, $this->tenantId));

    expect(LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->count())->toBe(3);
    assertBackfilledLedgerBalanced($this->tenantId);
});

it('skips orders the live ledger already holds', function (): void {
    paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'backfill-3', 'central');

    $this->artisan('finance:backfill-ledger', ['--tenant' => [$this->tenantId]])
        ->expectsOutputToContain('Posted 0 transaction(s) for 1 store(s).')
        ->assertSuccessful();

    expect(LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->count())->toBe(1)
        ->and(($this->balance)())->toBe(['payable' => 960, 'receivable' => 0, 'net' => 960]);
});

function assertBackfilledLedgerBalanced(int $tenantId): void
{
    foreach (LedgerTransaction::withoutTenantScope()->where('tenant_id', $tenantId)->with('entries')->get() as $transaction) {
        expect($transaction->entries->sum('debit'))->toBe($transaction->entries->sum('credit'));
    }
}
