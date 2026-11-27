<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyOrders;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Platform billing history (decision #113): old `payment_logs` (store subscription
 * payments, USD) → new `payment_logs` + one paid subscription `invoices` row per
 * captured payment (number INV-<year>-L<old id>, one line, no tax), so the ledger
 * backfill posts them as platform revenue. The raw gateway response (card fingerprint,
 * last4…) is not copied: only the gateway reference and the old ids.
 *
 * Old central `invoices` are customer ORDER invoices: the new system renders those from
 * the order itself, so they are not copied; each is checked against the migrated order
 * (`order_facts.grand_total`) and differences are reported.
 */
class BillingStep implements LegacyStepHandler
{
    private const CHUNK = 200;

    public function step(): LegacyStep
    {
        return LegacyStep::Billing;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();

        if ($context->source->hasTable($source, 'payment_logs')) {
            $this->paymentLogs($context);
        }

        if ($context->source->hasTable($source, 'invoices')) {
            $this->orderInvoices($context);
        }
    }

    private function paymentLogs(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $plans = $writer->connection()->table('plans')->pluck('name', 'id');

        $source->table('payment_logs')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer, $plans): void {
            $context->report->read('central', 'payment_logs', $rows->count());
            $owners = $writer->connection()->table('tenants')
                ->whereIn('id', $rows->map(fn (object $row): ?int => $writer->id('tenant', (string) $row->tenant_id))->filter(static fn (?int $id): bool => $id !== null && $id > 0)->all())
                ->get(['id', 'name', 'owner_name', 'owner_email'])->keyBy('id');

            $writer->transaction(function () use ($context, $writer, $rows, $plans, $owners): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $tenantId = $writer->id('tenant', (string) $row->tenant_id);

                    if ($tenantId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'payment_logs', $id, 'Payment of a store that was not migrated (skipped)');
                        $context->report->skipped('central', 'payment_logs');

                        continue;
                    }

                    $amount = LegacyValues::minor($row->amount);
                    $status = match ((string) $row->status) {
                        'paid', 'captured', 'succeeded', 'completed' => PaymentLogStatus::Captured,
                        'failed', 'cancelled' => PaymentLogStatus::Failed,
                        'refunded' => PaymentLogStatus::Refunded,
                        default => PaymentLogStatus::Pending,
                    };
                    $paidAt = $row->paid_at ?? ($status === PaymentLogStatus::Captured ? $row->created_at : null);
                    $planId = $writer->id('plan', $row->package_id);
                    $planName = $this->planName($plans->get($planId));
                    $invoiceId = null;

                    if ($status === PaymentLogStatus::Captured || $status === PaymentLogStatus::Refunded) {
                        $owner = $owners->get($tenantId);
                        $year = Carbon::parse($row->created_at ?? $paidAt ?? now())->year;
                        $invoiceId = $writer->upsert('invoice', $id, 'invoices', [
                            'number' => sprintf('INV-%d-L%04d', $year, $id),
                            'tenant_id' => $tenantId,
                            'type' => InvoiceType::Subscription->value,
                            'status' => ($status === PaymentLogStatus::Refunded ? InvoiceStatus::Refunded : InvoiceStatus::Paid)->value,
                            'currency' => 'USD',
                            'subtotal_minor' => $amount,
                            'tax_rate_bp' => 0,
                            'tax_minor' => 0,
                            'total_minor' => $amount,
                            'bill_to_name' => $owner->owner_name ?? $owner->name ?? null,
                            'bill_to_email' => $owner->owner_email ?? null,
                            'payment_method' => mb_substr((string) $row->gateway, 0, 32),
                            'payment_reference' => $row->reference,
                            'issued_at' => $row->created_at ?? $paidAt,
                            'due_at' => $row->created_at ?? $paidAt,
                            'paid_at' => $paidAt,
                            'meta' => ['legacy_payment_log_id' => $id, 'plan_id' => $planId !== null && $planId > 0 ? $planId : null],
                            'created_at' => $row->created_at,
                        ]);

                        $writer->syncPivot('invoice_items', 'invoice_id', $invoiceId, [[
                            'invoice_id' => $invoiceId,
                            'description' => mb_substr(($planName ?? 'Subscription').' — subscription', 0, 255),
                            'quantity' => 1,
                            'unit_amount_minor' => $amount,
                            'total_minor' => $amount,
                            'sort_order' => 0,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->created_at,
                        ]]);
                    }

                    $writer->upsert('payment_log', $id, 'payment_logs', [
                        'tenant_id' => $tenantId,
                        'invoice_id' => $invoiceId,
                        'gateway' => mb_substr((string) $row->gateway, 0, 32),
                        'purpose' => PaymentPurpose::Registration->value,
                        'status' => $status->value,
                        'amount_minor' => $amount,
                        'currency' => 'USD',
                        'gateway_reference' => $row->reference,
                        'payload' => ['legacy_payment_log_id' => $id, 'legacy_package_id' => $row->package_id !== null ? (int) $row->package_id : null],
                        'paid_at' => $paidAt,
                        'refunded_at' => $status === PaymentLogStatus::Refunded ? $row->updated_at : null,
                        'created_at' => $row->created_at,
                    ]);
                }
            });
        });
    }

    private function orderInvoices(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();

        $source->table('invoices')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer): void {
            $context->report->read('central', 'order_invoices', $rows->count());
            $pairs = [];

            foreach ($rows as $row) {
                $tenantId = $writer->id('tenant', (string) $row->tenant_id);
                $pairs[] = [(int) $tenantId, LegacyOrders::uuid((string) ($row->order_uuid ?? $row->order_number))];
            }

            $facts = LegacyOrders::facts($writer->connection(), $pairs);

            foreach ($rows as $index => $row) {
                [$tenantId, $uuid] = $pairs[$index];
                $fact = $facts[$tenantId.'|'.$uuid] ?? null;
                $context->report->skipped('central', 'order_invoices');

                if ($fact === null) {
                    continue;
                }

                if ((int) $fact->grand_total !== LegacyValues::minor($row->amount)) {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', 'order_invoices', (int) $row->id, 'Old order invoice amount differs from the migrated order grand total');
                }
            }
        });

        $context->report->note('Old order invoices are not copied: the new system renders the invoice from the order (checked against migrated orders where present).');
    }

    private function planName(mixed $name): ?string
    {
        if (is_string($name)) {
            $decoded = json_decode($name, true);

            return is_array($decoded) ? (string) ($decoded['en'] ?? reset($decoded)) : $name;
        }

        return null;
    }
}
