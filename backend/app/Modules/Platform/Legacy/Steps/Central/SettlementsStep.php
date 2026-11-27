<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Billing\Enums\PayoutMethod;
use App\Modules\Billing\Enums\PayoutStatus;
use App\Modules\Billing\Models\TenantPayout;
use App\Modules\Billing\Services\Ledger\FinancePostings;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyOrders;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Collection;

/**
 * Vendor settlements (vendor → platform payments for cash-collected orders) and tenant
 * payouts (platform → vendor) with their ledger postings (decision #113). Money in USD
 * cents, copied 1:1 (settlement amount = old total). Each paid row is posted through
 * FinancePostings, whose idempotency keys (`settlement:<id>`, `payout:<id>:paid`) make
 * re-runs no-ops; `legacy:reconcile` then compares old totals, new rows and ledger.
 * Old bank account numbers are not copied (payee keeps the bank name + last 4 only).
 */
class SettlementsStep implements LegacyStepHandler
{
    private const CHUNK = 200;

    public function __construct(private readonly FinancePostings $postings) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Settlements;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();

        if ($context->source->hasTable($source, 'vendor_settlements')) {
            $this->settlements($context);
        }

        if ($context->source->hasTable($source, 'tenant_payouts')) {
            $this->payouts($context);
        }
    }

    private function settlements(LegacyContext $context): void
    {
        $writer = $context->central;

        $context->source->central()->table('vendor_settlements')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer): void {
            $context->report->read('central', 'vendor_settlements', $rows->count());
            $tenants = $rows->mapWithKeys(fn (object $row): array => [(string) $row->tenant_id => $writer->id('tenant', (string) $row->tenant_id)]);
            $facts = LegacyOrders::facts($writer->connection(), $rows->map(static fn (object $row): array => [(int) $tenants[(string) $row->tenant_id], LegacyOrders::uuid((string) $row->order_uuid)])->all());
            $posted = [];

            $writer->transaction(function () use ($context, $writer, $rows, $tenants, $facts, &$posted): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $tenantId = $tenants[(string) $row->tenant_id];

                    if ($tenantId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'vendor_settlements', $id, 'Settlement of a store that was not migrated (skipped; listed apart in the reconciliation)');
                        $context->report->skipped('central', 'vendor_settlements');

                        continue;
                    }

                    $uuid = LegacyOrders::uuid((string) $row->order_uuid);
                    $fact = $facts[$tenantId.'|'.$uuid] ?? null;
                    $status = match ((string) $row->status) {
                        'paid', 'settled', 'completed' => SettlementStatus::Paid,
                        'failed', 'cancelled' => SettlementStatus::Failed,
                        default => SettlementStatus::Pending,
                    };
                    $productCost = LegacyValues::minor($row->product_cost);
                    $shippingCost = LegacyValues::minor($row->shipping_cost);
                    $total = LegacyValues::minor($row->total);

                    if ($total !== $productCost + $shippingCost + LegacyValues::minor($row->gateway_fee)) {
                        $context->report->issue(LegacyIssueLevel::Info, 'central', 'vendor_settlements', $id, 'Old settlement total ≠ product + shipping + gateway fee (total kept)');
                    }

                    $settlementId = $writer->upsert('order_settlement', $id, 'order_settlements', [
                        'reference' => mb_substr('ST-L'.$id, 0, 20),
                        'tenant_id' => $tenantId,
                        'order_uuid' => $uuid,
                        'order_number' => $fact !== null ? (string) $fact->order_number : LegacyOrders::fallbackNumber($uuid),
                        'amount' => $total,
                        'shipping_cost' => $shippingCost,
                        'product_cost' => $productCost,
                        'currency' => 'USD',
                        'gateway' => mb_substr((string) ($row->gateway_code ?? 'manual'), 0, 32),
                        'transaction_reference' => $row->transaction_id !== null ? mb_substr((string) $row->transaction_id, 0, 120) : null,
                        'note' => 'Legacy settlement '.$row->invoice_number,
                        'status' => $status->value,
                        'recorded_by_name' => 'Legacy migration',
                        'paid_at' => $status === SettlementStatus::Paid ? ($row->settled_at ?? $row->created_at) : null,
                        'created_at' => $row->created_at,
                    ]);

                    if ($status === SettlementStatus::Paid) {
                        $posted[] = $settlementId;
                    }
                }
            });

            if (! $context->dryRun()) {
                OrderSettlement::withoutTenantScope()->whereKey($posted)->get()->each(fn (OrderSettlement $settlement) => $this->postings->settlement($settlement));
            }
        });
    }

    private function payouts(LegacyContext $context): void
    {
        $writer = $context->central;

        $context->source->central()->table('tenant_payouts')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer): void {
            $context->report->read('central', 'tenant_payouts', $rows->count());
            $posted = [];

            $writer->transaction(function () use ($context, $writer, $rows, &$posted): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $tenantId = $writer->id('tenant', (string) $row->tenant_id);

                    if ($tenantId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'tenant_payouts', $id, 'Payout of a store that was not migrated (skipped; listed apart in the reconciliation)');
                        $context->report->skipped('central', 'tenant_payouts');

                        continue;
                    }

                    $status = PayoutStatus::tryFrom((string) $row->status) ?? match ((string) $row->status) {
                        'pending' => PayoutStatus::Scheduled,
                        'failed', 'rejected' => PayoutStatus::Cancelled,
                        default => PayoutStatus::Paid,
                    };
                    $method = match ((string) $row->method) {
                        'bank', 'bank_transfer' => PayoutMethod::BankTransfer,
                        'gateway' => PayoutMethod::Other,
                        default => PayoutMethod::tryFrom((string) $row->method) ?? PayoutMethod::Other,
                    };
                    $account = preg_replace('/\s+/', '', (string) $row->bank_account);
                    $payoutId = $writer->upsert('tenant_payout', $id, 'tenant_payouts', [
                        'reference' => LegacyUnique::value($writer, 'tenant_payouts', 'reference', 'PO-L'.$id, $writer->id('tenant_payout', $id), $id, 20),
                        'tenant_id' => $tenantId,
                        'amount' => LegacyValues::minor($row->amount),
                        'currency' => 'USD',
                        'method' => $method->value,
                        'status' => $status->value,
                        'transaction_reference' => $row->transaction_reference !== null ? mb_substr((string) $row->transaction_reference, 0, 120) : null,
                        'note' => $row->note,
                        'notify_vendor' => false,
                        'payee' => array_filter([
                            'bank' => $row->bank_name ?? $row->gateway_name,
                            'iban' => $account !== '' ? '•••• '.substr($account, -4) : null,
                            'name' => $row->tenant_name,
                        ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                        'created_by_name' => 'Legacy migration',
                        'paid_at' => $status === PayoutStatus::Paid ? ($row->paid_at ?? $row->created_at) : null,
                        'cancelled_at' => $status === PayoutStatus::Cancelled ? $row->updated_at : null,
                        'created_at' => $row->created_at,
                    ]);

                    if ($status === PayoutStatus::Paid) {
                        $posted[] = $payoutId;
                    }
                }
            });

            if (! $context->dryRun()) {
                TenantPayout::withoutTenantScope()->whereKey($posted)->get()->each(fn (TenantPayout $payout) => $this->postings->payoutPaid($payout));
            }
        });
    }
}
