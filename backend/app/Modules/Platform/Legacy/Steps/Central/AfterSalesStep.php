<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\AfterSales\Enums\InspectionResult;
use App\Modules\AfterSales\Enums\RefundMethod;
use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnMediaKind;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyMedia;
use App\Modules\Platform\Legacy\Support\LegacyOrders;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Platform\Legacy\Support\LegacyWriter;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central return requests (+ notes → events, media) and refunds (decision #113).
 *
 * Runs after the store steps: a return needs its order line, so it is migrated only
 * when the order exists in the new DB (`order_facts` + the store's `order_items`);
 * otherwise it is skipped and reported with its money. Refunds are money records and
 * are always kept: amounts copied 1:1 in cents, order uuid = the old order reference,
 * order number from `order_facts` (or `L-<uuid prefix>` when the order was not migrated).
 */
class AfterSalesStep implements LegacyStepHandler
{
    private const CHUNK = 200;

    public function __construct(private readonly LegacyMedia $media) {}

    public function step(): LegacyStep
    {
        return LegacyStep::AfterSales;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();

        if ($context->source->hasTable($source, 'return_requests')) {
            $this->returns($context);
        }

        if ($context->source->hasTable($source, 'refunds')) {
            $this->refunds($context);
        }
    }

    private function returns(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $reasons = $writer->connection()->table('return_reasons')->pluck('seller_fault', 'code')->map(static fn (mixed $fault): bool => (bool) $fault)->all();

        $source->table('return_requests')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer, $source, $reasons): void {
            $context->report->read('central', 'return_requests', $rows->count());
            $tenants = $rows->mapWithKeys(fn (object $row): array => [(string) $row->tenant_id => $writer->id('tenant', (string) $row->tenant_id)]);
            $facts = LegacyOrders::facts($writer->connection(), $rows->map(static fn (object $row): array => [(int) $tenants[(string) $row->tenant_id], LegacyOrders::uuid((string) $row->order_number)])->all());
            $items = $this->orderItems($facts);
            $ids = $rows->pluck('id')->map('intval')->all();
            $notes = $source->table('return_request_notes')->whereIn('return_request_id', $ids)->orderBy('id')->get()->groupBy('return_request_id');
            $media = $context->source->hasTable($source, 'return_request_media') ? $source->table('return_request_media')->whereIn('return_request_id', $ids)->orderBy('id')->get()->groupBy('return_request_id') : collect();

            $writer->transaction(function () use ($context, $writer, $rows, $tenants, $facts, $items, $notes, $media, $reasons): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $tenantId = $tenants[(string) $row->tenant_id];
                    $refund = LegacyValues::minor($row->refund_amount);

                    if ($tenantId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'return_requests', $id, 'Return of a store that was not migrated (skipped)');
                        $context->report->skipped('central', 'return_requests');

                        continue;
                    }

                    $uuid = LegacyOrders::uuid((string) $row->order_number);
                    $fact = $facts[$tenantId.'|'.$uuid] ?? null;
                    $item = $fact !== null ? $this->pickItem($writer, $items[$tenantId.'|'.$fact->order_id] ?? [], $row) : null;

                    if ($fact === null || $item === null) {
                        $context->report->issue(LegacyIssueLevel::Info, 'central', 'return_requests', $id, 'Return of an order that was not migrated (skipped; store orders not required for launch)');
                        $context->report->skipped('central', 'return_requests');

                        continue;
                    }

                    $status = $this->returnStatus((string) $row->status);
                    $reason = array_key_exists((string) $row->reason, $reasons) ? (string) $row->reason : 'other';
                    $reviewed = $row->reviewed_at ?? $row->updated_at;
                    $returnId = $writer->upsert('return_request', $id, 'return_requests', [
                        'reference' => LegacyUnique::value($writer, 'return_requests', 'reference', 'RMA-L'.$id, $writer->id('return_request', $id), $id, 32),
                        'tenant_id' => $tenantId,
                        'order_uuid' => $uuid,
                        'order_number' => (string) $fact->order_number,
                        'order_item_id' => (int) $item->id,
                        'store_product_id' => $item->store_product_id !== null ? (int) $item->store_product_id : null,
                        'product_name' => mb_substr((string) $item->name, 0, 255),
                        'variant_label' => $item->variant_label,
                        'quantity' => max(1, (int) $row->quantity),
                        'type' => ($row->type === 'exchange' ? ReturnType::Exchange : ReturnType::Return)->value,
                        'reason_code' => $reason,
                        'seller_fault' => $reasons[$reason] ?? false,
                        'description' => (string) ($row->description ?? $row->customer_note ?? ''),
                        'status' => $status->value,
                        'customer_id' => $fact->customer_id,
                        'customer_name' => $fact->customer_name,
                        'items_amount' => $refund,
                        'refund_amount' => $refund,
                        'inspection_result' => match ((string) $row->inspection_result) {
                            'passed', 'accepted' => InspectionResult::Accepted->value,
                            'failed', 'rejected' => InspectionResult::Failed->value,
                            default => null,
                        },
                        'inspection_notes' => $row->inspection_notes,
                        'restocked' => $row->restocked_at !== null,
                        'exchange_tracking_number' => $row->exchange_tracking_number !== null ? mb_substr((string) $row->exchange_tracking_number, 0, 64) : null,
                        'created_by_type' => ActorType::Customer->value,
                        'approved_at' => $status !== ReturnStatus::Pending && $status !== ReturnStatus::Rejected ? $reviewed : null,
                        'received_at' => $row->received_at,
                        'inspected_at' => $row->inspected_at,
                        'restocked_at' => $row->restocked_at,
                        'refunded_at' => $status === ReturnStatus::Refunded ? $row->updated_at : null,
                        'exchange_shipped_at' => $row->exchange_shipped_at,
                        'exchanged_at' => $row->exchange_completed_at,
                        'cancelled_at' => $row->cancelled_at,
                        'created_at' => $row->created_at,
                    ]);

                    $writer->syncPivot('return_request_events', 'return_request_id', $returnId, $notes->get($id, collect())->map(static fn (object $note): array => [
                        'return_request_id' => $returnId,
                        'type' => ReturnEventType::Note->value,
                        'note' => (string) $note->note,
                        'customer_visible' => (bool) $note->customer_visible,
                        'actor_type' => (ActorType::tryFrom((string) $note->author_type) ?? ($note->author_type === 'tenant' ? ActorType::Vendor : ActorType::System))->value,
                        'actor_id' => null,
                        'created_at' => $note->created_at,
                    ])->values()->all());

                    $files = [];

                    foreach ($media->get($id, collect()) as $file) {
                        $copied = $this->media->copy($context, 'return_request_media', (int) $file->id, (string) $file->file_path, 'after-sales/returns', LegacyMedia::DISK, (string) $row->tenant_id);

                        if ($copied !== null) {
                            $files[] = [
                                'return_request_id' => $returnId,
                                'disk' => 'public',
                                'path' => $copied['path'],
                                'original_name' => mb_substr(basename((string) $file->file_path), 0, 255),
                                'mime' => $copied['mime'],
                                'size' => $copied['size'],
                                'kind' => ($file->type === 'video' ? ReturnMediaKind::Video : ReturnMediaKind::Photo)->value,
                                'uploaded_by_type' => ActorType::Customer->value,
                                'created_at' => $file->created_at,
                                'updated_at' => $file->updated_at,
                            ];
                        }
                    }

                    $writer->syncPivot('return_request_media', 'return_request_id', $returnId, $files);
                }
            });
        });
    }

    private function refunds(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();

        $source->table('refunds')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer): void {
            $context->report->read('central', 'refunds', $rows->count());
            $tenants = $rows->mapWithKeys(fn (object $row): array => [(string) $row->tenant_id => $writer->id('tenant', (string) $row->tenant_id)]);
            $facts = LegacyOrders::facts($writer->connection(), $rows->map(static fn (object $row): array => [(int) $tenants[(string) $row->tenant_id], LegacyOrders::uuid((string) $row->order_number)])->all());

            $writer->transaction(function () use ($context, $writer, $rows, $tenants, $facts): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $tenantId = $tenants[(string) $row->tenant_id];

                    if ($tenantId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'refunds', $id, 'Refund of a store that was not migrated (skipped)');
                        $context->report->skipped('central', 'refunds');

                        continue;
                    }

                    $uuid = LegacyOrders::uuid((string) $row->order_number);
                    $fact = $facts[$tenantId.'|'.$uuid] ?? null;

                    if ($fact === null) {
                        $context->report->issue(LegacyIssueLevel::Info, 'central', 'refunds', $id, 'Refund kept for an order that was not migrated (order number L-<uuid>)');
                    }

                    foreach (['items_amount', 'shipping_amount', 'return_fee', 'amount'] as $column) {
                        if (LegacyValues::losesPrecision($row->{$column})) {
                            $context->report->issue(LegacyIssueLevel::Info, 'central', 'refunds', $id, "Sub-cent refund {$column} rounded to cents");
                        }
                    }

                    $processor = ActorType::tryFrom((string) $row->approved_by_type) ?? ($row->approved_by_type === 'tenant' ? ActorType::Vendor : null);
                    $writer->upsert('refund', $id, 'refunds', [
                        'reference' => LegacyUnique::value($writer, 'refunds', 'reference', (string) $row->reference, $writer->id('refund', $id), $id, 32),
                        'tenant_id' => $tenantId,
                        'order_uuid' => $uuid,
                        'order_number' => $fact !== null ? (string) $fact->order_number : LegacyOrders::fallbackNumber($uuid),
                        'return_request_id' => ($returnId = $writer->id('return_request', $row->return_request_id)) !== null && $returnId > 0 ? $returnId : null,
                        'source' => (RefundSource::tryFrom((string) $row->source) ?? RefundSource::Manual)->value,
                        'reason' => mb_substr((string) $row->reason, 0, 500),
                        'currency' => 'USD',
                        'items_amount' => LegacyValues::minor($row->items_amount),
                        'shipping_amount' => LegacyValues::minor($row->shipping_amount),
                        'return_fee' => LegacyValues::minor($row->return_fee),
                        'amount' => LegacyValues::minor($row->amount),
                        'method' => ($row->refund_method === 'manual' ? RefundMethod::Manual : RefundMethod::OriginalPayment)->value,
                        'gateway' => $row->gateway !== null ? mb_substr((string) $row->gateway, 0, 32) : null,
                        'original_transaction_id' => $row->original_transaction_id,
                        'gateway_refund_id' => $row->gateway_refund_id,
                        'status' => $this->refundStatus((string) $row->status)->value,
                        'failure_reason' => $row->failure_reason !== null ? mb_substr((string) $row->failure_reason, 0, 500) : null,
                        'requested_by_type' => (ActorType::tryFrom((string) $row->requested_by_type) ?? ($row->requested_by_type === 'tenant' ? ActorType::Vendor : ActorType::System))->value,
                        'processed_by_type' => $processor?->value,
                        'processed_by_name' => $row->approved_by_name !== null ? mb_substr((string) $row->approved_by_name, 0, 120) : null,
                        'notes' => $row->notes,
                        'requested_at' => $row->requested_at ?? $row->created_at ?? now(),
                        'processed_at' => $row->processed_at ?? $row->approved_at,
                        'created_at' => $row->created_at,
                    ]);
                }
            });
        });
    }

    /**
     * The store order lines of the matched orders, one tenant DB visit per store and chunk.
     *
     * @param  array<string, object>  $facts
     * @return array<string, list<object>> "<tenant>|<order id>" => lines
     */
    private function orderItems(array $facts): array
    {
        $out = [];

        foreach (collect($facts)->filter(static fn (object $fact): bool => $fact->order_id !== null)->groupBy(static fn (object $fact, string $key): string => strtok($key, '|')) as $tenantId => $tenantFacts) {
            $tenant = Tenant::query()->find((int) $tenantId);

            if ($tenant === null) {
                continue;
            }

            $lines = $tenant->run(static fn () => DB::connection('tenant')->table('order_items')
                ->whereIn('order_id', $tenantFacts->pluck('order_id')->map('intval')->all())
                ->orderBy('id')
                ->get(['id', 'order_id', 'product_id', 'product_source', 'store_product_id', 'name', 'variant_label']));

            foreach ($lines as $line) {
                $out[$tenantId.'|'.$line->order_id][] = $line;
            }
        }

        return $out;
    }

    /**
     * The order line of a return: the line of the same central product, else the only / first line.
     *
     * @param  list<object>  $lines
     */
    private function pickItem(LegacyWriter $writer, array $lines, object $row): ?object
    {
        if ($lines === []) {
            return null;
        }

        $product = ($row->product_type ?? null) === 'central' ? $writer->id('product', $row->catalog_product_id ?? null) : null;

        foreach ($lines as $line) {
            if ($product !== null && $line->product_source === 'central' && (int) $line->product_id === $product) {
                return $line;
            }
        }

        return $lines[0];
    }

    private function returnStatus(string $old): ReturnStatus
    {
        return match ($old) {
            'received', 'item_received' => ReturnStatus::ItemReceived,
            'exchange_completed', 'exchanged', 'completed' => ReturnStatus::Exchanged,
            'forwarded', 'under_review' => ReturnStatus::Pending,
            default => ReturnStatus::tryFrom($old) ?? ReturnStatus::Pending,
        };
    }

    private function refundStatus(string $old): RefundStatus
    {
        return match ($old) {
            'approved' => RefundStatus::Processing,
            'cancelled' => RefundStatus::Rejected,
            default => RefundStatus::tryFrom($old) ?? RefundStatus::Pending,
        };
    }
}
