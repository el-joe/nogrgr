<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\PaymentRequestStatus;
use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Enums\RequestPriority;
use App\Modules\Catalog\Services\Requests\RequestThreadService;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyMedia;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Platform\Legacy\Support\LegacyWriter;

/**
 * Vendor → platform catalogue requests (decision #113): product, brand and
 * manufacturing requests with their threads (`catalog_request_messages`, one morph
 * table), attachments (copied to the private `central` disk under
 * `requests/<kind>/<id>/legacy/...`) and payment requests (USD cents).
 *
 * As in the new submit flow, request-level files (old `attachments` / `images`) hang on
 * an opening vendor message without body. Requests of stores that were not migrated are
 * skipped and reported.
 */
class RequestsStep implements LegacyStepHandler
{
    public function __construct(private readonly LegacyMedia $media) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Requests;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();

        foreach ([[RequestKind::Product, 'product_requests'], [RequestKind::Brand, 'brand_requests'], [RequestKind::Manufacturing, 'manufacturing_requests']] as [$kind, $table]) {
            if ($context->source->hasTable($source, $table)) {
                $this->requests($context, $kind, $table);
            }
        }
    }

    private function requests(LegacyContext $context, RequestKind $kind, string $table): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $rows = $source->table($table)->orderBy('id')->get();
        $foreign = $kind->value.'_request_id';
        $messageTable = $kind->value.'_request_messages';
        $paymentTable = $kind->value.'_payment_requests';
        $messages = $context->source->hasTable($source, $messageTable) ? $source->table($messageTable)->whereIn($foreign, $rows->pluck('id')->all())->orderBy('id')->get()->groupBy($foreign) : collect();
        $payments = $context->source->hasTable($source, $paymentTable) ? $source->table($paymentTable)->whereIn($foreign, $rows->pluck('id')->all())->orderBy('id')->get()->groupBy($foreign) : collect();
        $morph = $kind->morphAlias();
        $newTable = 'catalog_'.$kind->value.'_requests';

        $writer->transaction(function () use ($context, $writer, $kind, $table, $rows, $messages, $payments, $morph, $newTable): void {
            foreach ($rows as $row) {
                $context->report->read('central', $table);
                $id = (int) $row->id;
                $tenantId = $writer->id('tenant', (string) $row->tenant_id);

                if ($tenantId === null) {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', $table, $id, 'Request of a store that was not migrated (skipped)');
                    $context->report->skipped('central', $table);

                    continue;
                }

                $thread = $messages->get($id, collect());
                $lastMessage = $row->last_reply_at ?? $thread->max('created_at');
                $requestId = $writer->upsert($morph, $id, $newTable, $this->attributes($writer, $kind, $row) + [
                    'tenant_id' => $tenantId,
                    'admin_seen_at' => (bool) ($row->admin_has_unread ?? false) ? null : $row->updated_at,
                    'last_message_at' => $lastMessage,
                    'created_at' => $row->created_at,
                ]);

                $files = LegacyValues::json($row->attachments ?? $row->images ?? null) ?? [];

                if ($files !== []) {
                    $this->message($context, $writer, $kind, $morph, $requestId, $tenantId, (string) $row->tenant_id, 'opening:'.$id, (object) [
                        'sender_type' => 'tenant', 'sender_name' => 'Vendor', 'body' => null, 'attachments' => $files, 'created_at' => $row->created_at, 'updated_at' => $row->created_at,
                    ]);
                }

                foreach ($thread as $message) {
                    $context->report->read('central', $kind->value.'_request_messages');
                    $this->message($context, $writer, $kind, $morph, $requestId, $tenantId, (string) $row->tenant_id, (string) $message->id, $message);
                }

                foreach ($payments->get($id, collect()) as $payment) {
                    $context->report->read('central', $kind->value.'_payment_requests');
                    $writer->upsert('request_payment:'.$kind->value, (int) $payment->id, 'catalog_request_payments', [
                        'requestable_type' => $morph,
                        'requestable_id' => $requestId,
                        'tenant_id' => $tenantId,
                        'label' => mb_substr((string) $payment->label, 0, 255),
                        'amount' => LegacyValues::minor($payment->amount),
                        'currency' => 'USD',
                        'status' => (PaymentRequestStatus::tryFrom((string) $payment->status) ?? PaymentRequestStatus::Pending)->value,
                        'note' => $payment->notes,
                        'paid_at' => $payment->paid_at,
                        'created_at' => $payment->created_at,
                    ]);
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(LegacyWriter $writer, RequestKind $kind, object $row): array
    {
        return match ($kind) {
            RequestKind::Product => [
                'title' => mb_substr((string) $row->title, 0, 255),
                'description' => (string) $row->description,
                'product_url' => $row->product_url,
                'status' => (match ((string) $row->status) {
                    'reviewing', 'in_progress' => ProductRequestStatus::InReview,
                    'approved', 'completed', 'added' => ProductRequestStatus::AddedToCatalog,
                    'cancelled' => ProductRequestStatus::Rejected,
                    default => ProductRequestStatus::tryFrom((string) $row->status) ?? ProductRequestStatus::Pending,
                })->value,
                'priority' => (RequestPriority::tryFrom((string) $row->priority) ?? RequestPriority::Normal)->value,
            ],
            RequestKind::Brand => [
                'brand_name' => mb_substr((string) $row->title, 0, 255),
                'description' => $row->description,
                'status' => (match ((string) $row->status) {
                    'approved', 'reviewing' => BrandRequestStatus::UnderReview,
                    default => BrandRequestStatus::tryFrom((string) $row->status) ?? BrandRequestStatus::Pending,
                })->value,
                'admin_notes' => $row->admin_notes,
            ],
            RequestKind::Manufacturing => [
                'product_id' => ($product = $writer->id('product', $row->product_id)) !== null && $product > 0 ? $product : null,
                'product_name' => mb_substr((string) $row->product_name, 0, 255),
                'description' => $row->description,
                'reference_url' => $row->reference_url ?? null,
                'quantity' => max(1, (int) $row->quantity),
                'status' => (match ((string) $row->status) {
                    'pending' => ManufacturingRequestStatus::Requested,
                    'approved', 'in_progress' => ManufacturingRequestStatus::InProduction,
                    'completed' => ManufacturingRequestStatus::Delivered,
                    default => ManufacturingRequestStatus::tryFrom((string) $row->status) ?? ManufacturingRequestStatus::Requested,
                })->value,
                'admin_notes' => $row->admin_notes,
            ],
        };
    }

    private function message(LegacyContext $context, LegacyWriter $writer, RequestKind $kind, string $morph, int $requestId, int $tenantId, string $legacyTenantId, string $legacyKey, object $message): void
    {
        $sender = $message->sender_type === 'admin' ? MessageSender::Admin : MessageSender::Vendor;
        $messageId = $writer->upsert('request_message:'.$kind->value, $legacyKey, 'catalog_request_messages', [
            'requestable_type' => $morph,
            'requestable_id' => $requestId,
            'tenant_id' => $tenantId,
            'sender' => $sender->value,
            'sender_name' => mb_substr((string) $message->sender_name, 0, 255),
            'body' => $message->body ?? $message->message ?? null,
            'read_at' => $message->created_at,
            'created_at' => $message->created_at,
        ]);

        $rows = [];

        foreach (is_array($message->attachments ?? null) ? $message->attachments : (LegacyValues::json($message->attachments ?? null) ?? []) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $copied = $this->media->copy($context, $kind->value.'_request_attachments', $legacyKey, $path, 'requests/'.$kind->value.'/'.max(0, $requestId), RequestThreadService::DISK, $legacyTenantId);

            if ($copied !== null) {
                $rows[] = [
                    'message_id' => $messageId,
                    'disk' => RequestThreadService::DISK,
                    'path' => $copied['path'],
                    'original_name' => mb_substr(basename($path), 0, 255),
                    'mime_type' => $copied['mime'],
                    'size' => $copied['size'],
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at ?? $message->created_at,
                ];
            }
        }

        $writer->syncPivot('catalog_request_attachments', 'message_id', $messageId, $rows);
    }
}
