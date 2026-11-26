<?php

use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\Billing\Enums\LedgerAccount;
use App\Modules\Billing\Enums\LedgerDirection;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Enums\PayoutStatus;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\LedgerEntry;
use App\Modules\Billing\Models\LedgerTransaction;
use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Notifications\FinanceAlertNotification;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Billing\Services\Ledger\FinancePostings;
use App\Modules\Billing\Services\Ledger\LedgerService;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Integrations\Enums\IntegrationSection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Commerce/FulfilmentHelpers.php';

/*
| Ledger, settlements, payouts and transactions (ADM-050…053, VEN-062…064, decision #72).
| Golden order (2 × central in SA): grand 63.80, platform 54.20, vendor 9.60.
|   collected by the platform (source central) → vendor_payable 9.60;
|   collected by the store   (source tenant)  → vendor_receivable 54.20.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');
    $this->shop = shopSetup();
    shopGateway($this->shop->tenant);
    $this->owner = $this->createVendorOwner($this->shop->tenant);
    $this->customer = shopCustomer($this->shop);
    $this->tenantId = (int) $this->shop->tenant->id;
    $this->balance = fn (?int $tenantId = null): array => app(LedgerService::class)->balanceOf($tenantId ?? $this->tenantId);
    $this->admin = fn (?array $permissions = null) => $this->actingAs(commerceAdmin($permissions), 'admin');
    $this->refund = function (Order $order, int $amount, RefundStatus $status = RefundStatus::Completed): Refund {
        return Refund::withoutTenantScope()->create([
            'reference' => 'RF-T'.random_int(10000, 99999),
            'tenant_id' => $this->tenantId,
            'order_uuid' => $order->uuid,
            'order_number' => $order->number,
            'source' => RefundSource::Manual,
            'reason' => 'Test',
            'currency' => 'USD',
            'amount' => $amount,
            'items_amount' => $amount,
            'method' => RefundMethod::OriginalPayment,
            'gateway' => 'stripe',
            'payment_source' => 'central',
            'status' => $status,
            'requested_by_type' => ActorType::Admin,
            'requested_at' => now(),
        ]);
    };
});

/**
 * Every transaction is balanced and the running net equals Σ net changes.
 */
function assertLedgerConsistent(int $tenantId): void
{
    $running = 0;

    foreach (LedgerTransaction::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id')->with('entries')->get() as $transaction) {
        expect($transaction->entries->sum('debit'))->toBe($transaction->entries->sum('credit'));
        $running += $transaction->net_amount;
        expect($transaction->balance_after)->toBe($running);
    }

    $payable = LedgerEntry::withoutTenantScope()->where('tenant_id', $tenantId)->where('account', LedgerAccount::VendorPayable->value);
    $receivable = LedgerEntry::withoutTenantScope()->where('tenant_id', $tenantId)->where('account', LedgerAccount::VendorReceivable->value);
    $net = ((int) (clone $payable)->sum('credit') - (int) (clone $payable)->sum('debit')) - ((int) (clone $receivable)->sum('debit') - (int) (clone $receivable)->sum('credit'));

    expect($running)->toBe($net);
}

it('posts the golden vendor share of a platform-collected order once, balanced', function (): void {
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-1', 'central');

    expect(($this->balance)())->toBe(['payable' => 960, 'receivable' => 0, 'net' => 960]);

    $transaction = LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->sole();
    expect($transaction->source)->toBe(LedgerSource::Order)
        ->and($transaction->direction)->toBe(LedgerDirection::Credit)
        ->and($transaction->amount)->toBe(960)
        ->and($transaction->reference)->toStartWith('TX-')
        ->and($transaction->entries)->toHaveCount(2);

    // Replaying the event (or the posting) adds nothing.
    $this->shop->tenant->run(fn () => OrderPaid::dispatch($order, $this->tenantId));
    app(FinancePostings::class)->order($this->tenantId, $order, 'order:'.$this->tenantId.':'.$order->uuid.':paid');

    expect(LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->count())->toBe(1);
    assertLedgerConsistent($this->tenantId);
});

it('posts the golden platform share of a store-collected order and matches OrderSettlement', function (): void {
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-2', 'tenant');

    expect(($this->balance)())->toBe(['payable' => 0, 'receivable' => 5420, 'net' => -5420]);

    $figures = app(OrderFinanceService::class)->figures($order, $this->tenantId);
    expect($figures->owedToPlatform->minor)->toBe(5420);
    assertLedgerConsistent($this->tenantId);
});

it('lowers the vendor share on partial refunds and voids it on a full refund (replay-safe)', function (): void {
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-3', 'central');

    $partial = ($this->refund)($order, 500);
    RefundCompleted::dispatch($partial);
    RefundCompleted::dispatch($partial);

    expect(($this->balance)()['payable'])->toBe(460)
        ->and(LedgerTransaction::withoutTenantScope()->where('source', LedgerSource::Refund->value)->count())->toBe(1)
        ->and(LedgerTransaction::withoutTenantScope()->where('source', LedgerSource::Refund->value)->sole()->direction)->toBe(LedgerDirection::Reversal);

    // Refunds above the vendor share (seller-fault return): the store owes the shortfall (QA-P5-011).
    RefundCompleted::dispatch(($this->refund)($order, 2500));

    expect(($this->balance)())->toBe(['payable' => 0, 'receivable' => 2040, 'net' => -2040])
        ->and(app(OrderFinanceService::class)->figures($order, $this->tenantId)->owedToPlatform->minor)->toBe(2040);

    RefundCompleted::dispatch(($this->refund)($order, 3380));

    expect(($this->balance)())->toBe(['payable' => 0, 'receivable' => 0, 'net' => 0]);
    assertLedgerConsistent($this->tenantId);
});

it('lists paid invoices as net-zero subscription transactions', function (): void {
    $invoice = Invoice::factory()->create(['tenant_id' => $this->tenantId, 'total_minor' => 5900, 'paid_at' => now()]);
    InvoicePaid::dispatch($invoice);
    InvoicePaid::dispatch($invoice);

    $transaction = LedgerTransaction::withoutTenantScope()->where('tenant_id', $this->tenantId)->sole();
    expect($transaction->net_amount)->toBe(0)
        ->and($transaction->amount)->toBe(5900)
        ->and($transaction->direction)->toBe(LedgerDirection::Debit)
        ->and(($this->balance)()['net'])->toBe(0);
});

it('protects every finance route', function (): void {
    $paths = ['/admin/finance/ledger', '/admin/finance/ledger/data', '/admin/finance/settlements', '/admin/finance/payouts', '/admin/finance/payouts/create', '/admin/finance/transactions', '/admin/finance/transactions/data'];

    foreach ($paths as $path) {
        $this->get($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
    }

    ($this->admin)([]);

    foreach ([...$paths, "/admin/finance/ledger/{$this->tenantId}", '/admin/finance/settlements/data', '/admin/finance/payouts/data'] as $path) {
        $this->getJson($this->centralUrl($path))->assertForbidden();
    }

    ($this->admin)(['finance.ledger.view']);
    $this->getJson($this->centralUrl("/admin/finance/ledger/{$this->tenantId}"))->assertOk();
    $this->postJson($this->centralUrl("/admin/finance/ledger/{$this->tenantId}/settle"), ['amount' => 1, 'method' => 'cash', 'reference' => 'X'])->assertForbidden();
    $this->postJson($this->centralUrl('/admin/finance/payouts'), [])->assertForbidden();
});

it('shows the ledger list, a store ledger, its entries, statement and export', function (): void {
    paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-4', 'tenant');
    ($this->admin)();

    $this->get($this->centralUrl('/admin/finance/ledger'))->assertOk()->assertSee(__('billing::finance.ledger.kpi.owed_to_platform'))->assertSee('$54.20');

    $data = fn (string $query = ''): array => $this->getJson($this->centralUrl('/admin/finance/ledger/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
    $row = collect($data()['data'])->first(fn (array $row): bool => str_contains($row['tenant'], $this->shop->tenant->name));
    expect($row['receivable'])->toContain('$54.20')
        ->and($row['status'])->toContain(__('billing::finance.ledger.status.due'))
        ->and($data('&balance=owes_platform')['recordsFiltered'])->toBe(1)
        ->and($data('&balance=platform_owes')['recordsFiltered'])->toBe(0);

    $this->get($this->centralUrl("/admin/finance/ledger/{$this->tenantId}"))->assertOk()->assertSee('-$54.20');
    $entries = $this->getJson($this->centralUrl("/admin/finance/ledger/{$this->tenantId}/entries?draw=1&start=0&length=10"))->assertOk()->json('data');
    expect($entries)->toHaveCount(1)->and($entries[0]['debit'])->toContain('$54.20');

    $this->get($this->centralUrl("/admin/finance/ledger/{$this->tenantId}/statement"))->assertOk()->assertSee(__('billing::finance.ledger.print'));
    expect($this->get($this->centralUrl('/admin/finance/ledger/export'))->assertOk()->streamedContent())->toContain($this->shop->tenant->name)->toContain('54.20');
    $this->getJson($this->centralUrl('/admin/finance/ledger/99999'))->assertNotFound();
});

it('marks a settlement paid from the ledger: validation, FIFO allocation, ledger and formulas agree', function (): void {
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-5', 'tenant');
    ($this->admin)();
    $url = $this->centralUrl("/admin/finance/ledger/{$this->tenantId}/settle");

    $this->get($url, xhrHeaders())->assertOk()->assertSee('54.20');
    $this->postJson($url, ['amount' => 10, 'method' => 'bank_transfer'])->assertUnprocessable()->assertJsonValidationErrors('reference');
    $this->postJson($url, ['amount' => 10, 'method' => 'bogus', 'reference' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('method');
    $this->postJson($url, ['amount' => 60, 'method' => 'cash', 'reference' => 'X'])->assertUnprocessable()->assertJsonPath('data.reason', 'settlement_exceeds');
    $this->postJson($url, ['amount' => 1, 'method' => 'payout_offset'])->assertUnprocessable()->assertJsonPath('data.reason', 'offset_exceeds');

    $this->post($url, ['amount' => '20.00', 'method' => 'bank_transfer', 'reference' => 'TRX-1', 'attachment' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();

    $row = OrderSettlement::withoutTenantScope()->where('tenant_id', $this->tenantId)->sole();
    expect($row->status)->toBe(SettlementStatus::Paid)
        ->and($row->amount)->toBe(2000)
        ->and($row->method)->toBe('bank_transfer')
        ->and($row->reference)->toStartWith('ST-')
        ->and($row->attachment_path)->not->toBeNull()
        ->and(($this->balance)()['receivable'])->toBe(3420)
        ->and(app(OrderFinanceService::class)->figures($order, $this->tenantId)->owedToPlatform->minor)->toBe(3420);

    Notification::assertSentTimes(FinanceAlertNotification::class, 1);

    $this->getJson($this->centralUrl('/admin/finance/settlements/data?draw=1&start=0&length=10&status=paid'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    $this->get($this->centralUrl('/admin/finance/settlements/'.$row->reference), xhrHeaders())->assertOk()->assertSee('TRX-1')->assertSee('#'.$order->number);
    $this->get($this->centralUrl('/admin/finance/settlements/'.$row->reference.'/attachment'))->assertOk();
    $this->get($this->centralUrl('/admin/finance/settlements'))->assertOk();
    assertLedgerConsistent($this->tenantId);
});

it('runs a payout through scheduled, processing, paid and cancelled with ledger postings', function (): void {
    paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-6', 'central');
    ($this->admin)();

    $this->get($this->centralUrl('/admin/finance/payouts/create'))->assertOk()->assertSee(__('billing::finance.payouts.pick_store_title'));
    $this->get($this->centralUrl('/admin/finance/payouts/create?tenant_id='.$this->tenantId))->assertOk()->assertSee('$9.60');

    $store = fn (array $data) => $this->postJson($this->centralUrl('/admin/finance/payouts'), $data + ['tenant_id' => $this->tenantId, 'method' => 'bank_transfer', 'scheduled_for' => now()->toDateString()]);
    $store(['amount' => ''])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $store(['amount' => 10])->assertUnprocessable()->assertJsonPath('data.reason', 'payout_exceeds');
    $store(['amount' => 5, 'notify_vendor' => 1, 'transaction_reference' => null])->assertCreated();

    $payout = TenantPayout::withoutTenantScope()->sole();
    expect($payout->status)->toBe(PayoutStatus::Scheduled)->and($payout->reference)->toStartWith('PO-')->and($payout->amount)->toBe(500);
    Notification::assertSentTimes(FinanceAlertNotification::class, 1);

    // Open payouts lower what is still available.
    $store(['amount' => 5])->assertUnprocessable()->assertJsonPath('data.reason', 'payout_exceeds');

    $update = fn (array $data) => $this->putJson($this->centralUrl("/admin/finance/payouts/{$payout->id}"), $data);
    $this->get($this->centralUrl("/admin/finance/payouts/{$payout->id}/edit"), xhrHeaders())->assertOk();
    $update(['status' => 'processing'])->assertOk();
    $update(['status' => 'paid'])->assertUnprocessable()->assertJsonValidationErrors('transaction_reference');
    $update(['status' => 'paid', 'transaction_reference' => 'TRX-9'])->assertOk();

    expect(($this->balance)()['payable'])->toBe(460)
        ->and(LedgerTransaction::withoutTenantScope()->where('source', 'payout')->sole()->direction)->toBe(LedgerDirection::Debit);

    $update(['status' => 'scheduled', 'transaction_reference' => 'TRX-9'])->assertUnprocessable()->assertJsonPath('data.reason', 'payout_transition');
    $this->postJson($this->centralUrl("/admin/finance/payouts/{$payout->id}/cancel"))->assertOk();
    $this->postJson($this->centralUrl("/admin/finance/payouts/{$payout->id}/cancel"))->assertUnprocessable();

    expect(($this->balance)()['payable'])->toBe(960)
        ->and($payout->fresh()?->status)->toBe(PayoutStatus::Cancelled);

    $this->getJson($this->centralUrl('/admin/finance/payouts/data?draw=1&start=0&length=10&status=cancelled'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    $this->get($this->centralUrl("/admin/finance/payouts/{$payout->id}"), xhrHeaders())->assertOk()->assertSee('TRX-9');
    $this->get($this->centralUrl('/admin/finance/payouts'))->assertOk();
    assertLedgerConsistent($this->tenantId);
});

it('lists transactions with direction and source filters, the detail modal and export', function (): void {
    paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-7', 'central');
    $other = shopSetup('other-shop');
    ($this->admin)();

    $data = fn (string $query = ''): array => $this->getJson($this->centralUrl('/admin/finance/transactions/data?draw=1&start=0&length=10'.$query))->assertOk()->json();
    expect($data()['recordsFiltered'])->toBe(1)
        ->and($data('&direction=credit')['recordsFiltered'])->toBe(1)
        ->and($data('&direction=debit')['recordsFiltered'])->toBe(0)
        ->and($data('&source=payout')['recordsFiltered'])->toBe(0)
        ->and($data('&tenant_id='.$other->tenant->id)['recordsFiltered'])->toBe(0);

    $transaction = LedgerTransaction::withoutTenantScope()->sole();
    $this->get($this->centralUrl('/admin/finance/transactions'))->assertOk();
    $this->get($this->centralUrl("/admin/finance/transactions/{$transaction->id}"), xhrHeaders())->assertOk()->assertSee((string) $transaction->reference)->assertSee(__('billing::enums.ledger_account.vendor_payable'));
    expect($this->get($this->centralUrl('/admin/finance/transactions/export'))->assertOk()->streamedContent())->toContain((string) $transaction->reference);
});

it('shows the store its money flow only: purchases owed, settlements and payouts', function (): void {
    $order = paidShopOrder($this->shop, $this->customer, [$this->shop->catalog->id => 2], 'ledger-key-8', 'tenant');
    $url = fn (string $path) => $this->tenantUrl($this->shop->tenant, 'admin/finance/'.$path);

    $this->get($url('purchases'))->assertRedirect($this->tenantUrl($this->shop->tenant, 'login'));

    $staff = $this->shop->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view']);

        return $staff;
    });
    $this->actingAsVendor($staff, $this->shop->tenant);

    foreach (['purchases', 'purchases/data', 'settlements', 'settlements/data', 'payouts', 'payouts/data'] as $path) {
        $this->getJson($url($path))->assertForbidden();
    }

    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $this->get($url('purchases'))->assertOk()->assertSee('$54.20');
    $rows = $this->getJson($url('purchases/data?draw=1&start=0&length=10'))->assertOk()->json('data');
    expect($rows)->toHaveCount(1)->and($rows[0]['order'])->toContain($order->number)->and($rows[0]['owed'])->toContain('$54.20');
    expect($this->get($url('purchases/export'))->assertOk()->streamedContent())->toContain($order->number);

    // Another store's settlement and payout never show.
    $other = shopSetup('other-store');
    OrderSettlement::withoutTenantScope()->create(['reference' => 'ST-9999-ZZZ', 'tenant_id' => $other->tenant->id, 'order_uuid' => $order->uuid, 'order_number' => 'X1', 'amount' => 100, 'currency' => 'USD', 'gateway' => 'manual', 'status' => SettlementStatus::Paid, 'paid_at' => now()]);
    TenantPayout::withoutTenantScope()->create(['reference' => 'PO-1', 'tenant_id' => $other->tenant->id, 'amount' => 100, 'currency' => 'USD', 'method' => 'bank_transfer', 'status' => PayoutStatus::Scheduled]);

    $this->actingAsVendor($this->owner, $this->shop->tenant);
    $this->get($url('settlements'))->assertOk();
    $this->get($url('payouts'))->assertOk();
    $this->getJson($url('settlements/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsFiltered', 0);
    $this->getJson($url('payouts/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsFiltered', 0);
    $this->get($url('settlements/ST-9999-ZZZ'), xhrHeaders())->assertNotFound();
});

it('reads the overdue threshold from Billing settings, not a constant (decision #69)', function (): void {
    $opened = now()->subDays(15)->toDateTimeString();

    expect(FinancePageService::status(500, $opened, null))->toBe('due')
        ->and(FinancePageService::status(0, $opened, null))->toBe('clear');

    $this->useSettings(IntegrationSection::Billing, ['settlement_overdue_days' => 10]);

    expect(FinancePageService::overdueDays())->toBe(10)
        ->and(FinancePageService::status(500, $opened, null))->toBe('overdue')
        ->and(FinancePageService::status(500, $opened, now()->subDays(3)->toDateTimeString()))->toBe('due');
});
