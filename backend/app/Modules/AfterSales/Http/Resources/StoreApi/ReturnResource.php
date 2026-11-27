<?php

namespace App\Modules\AfterSales\Http\Resources\StoreApi;

use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Models\ReturnReason;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Models\ReturnRequestEvent;
use App\Modules\AfterSales\Models\ReturnRequestMedia;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Shared\Routing\StoreApiSignedUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer view of a return (list row; detail adds the customer-visible timeline, the
 * evidence through signed URLs and the refunds). Internal notes, inspection notes and
 * staff ids are never exposed.
 *
 * @property ReturnRequest $resource
 */
class ReturnResource extends JsonResource
{
    public const MEDIA_URL_MINUTES = 30;

    public bool $detailed = false;

    /**
     * @var array<string, ReturnReason>|null
     */
    public ?array $reasons = null;

    /**
     * @param  array<string, ReturnReason>  $reasons  code => reason
     */
    public static function detail(ReturnRequest $return, array $reasons = []): self
    {
        $resource = new self($return);
        $resource->detailed = true;
        $resource->reasons = $reasons;

        return $resource;
    }

    /**
     * @param  array<string, ReturnReason>  $reasons
     */
    public static function row(ReturnRequest $return, array $reasons = []): self
    {
        $resource = new self($return);
        $resource->reasons = $reasons;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $return = $this->resource;
        $money = app(MoneyPresenter::class);
        $reason = $this->reasons[$return->reason_code] ?? null;
        $visible = $return->events->filter(static fn (ReturnRequestEvent $event): bool => self::isCustomerVisible($event));
        $lastMessage = $visible->first(static fn (ReturnRequestEvent $event): bool => $event->customer_visible && trim((string) $event->note) !== '');

        $data = [
            'id' => $return->id,
            'reference' => $return->reference,
            'order_uuid' => $return->order_uuid,
            'order_number' => $return->order_number,
            'order_item_id' => $return->order_item_id,
            'product_name' => $return->product_name,
            'variant_label' => $return->variant_label,
            'quantity' => $return->quantity,
            'type' => $return->type->value,
            'type_label' => $return->type->label(),
            'reason' => ['code' => $return->reason_code, 'name' => $reason->name ?? $return->reason_code],
            'status' => $return->status->value,
            'status_label' => $return->status->label(),
            'step' => $return->status->step(),
            'refund_amount' => $money->present($return->refund_amount),
            'last_message' => $lastMessage?->note,
            'can_withdraw' => $return->status->isWithdrawable(),
            'can_reply' => $return->status->isOpen(),
            'created_at' => $return->created_at?->toIso8601String(),
            'updated_at' => $return->updated_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return $data + [
            'description' => $return->description,
            'amounts' => [
                'items' => $money->present($return->items_amount),
                'shipping' => $money->present($return->shipping_amount),
                'return_fee' => $money->present($return->return_fee),
                'refund' => $money->present($return->refund_amount),
            ],
            'exchange_tracking_number' => $return->exchange_tracking_number,
            'dates' => [
                'approved_at' => $return->approved_at?->toIso8601String(),
                'received_at' => $return->received_at?->toIso8601String(),
                'inspected_at' => $return->inspected_at?->toIso8601String(),
                'refunded_at' => $return->refunded_at?->toIso8601String(),
                'exchange_shipped_at' => $return->exchange_shipped_at?->toIso8601String(),
                'exchanged_at' => $return->exchanged_at?->toIso8601String(),
                'cancelled_at' => $return->cancelled_at?->toIso8601String(),
                'closed_at' => $return->closed_at?->toIso8601String(),
            ],
            'timeline' => $visible->sortBy('id')->values()->map(static fn (ReturnRequestEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type->value,
                'from_status' => $event->from_status?->value,
                'to_status' => $event->to_status?->value,
                'status_label' => $event->to_status?->label(),
                'message' => $event->type === ReturnEventType::Status && ! $event->customer_visible ? null : $event->note,
                'author' => $event->actor_type === ActorType::Customer ? 'customer' : 'store',
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
            'media' => $return->media->filter(static fn (ReturnRequestMedia $media): bool => $media->uploaded_by_type === ActorType::Customer)
                ->values()
                ->map(static fn (ReturnRequestMedia $media): array => [
                    'id' => $media->id,
                    'kind' => $media->kind->value,
                    'name' => $media->original_name,
                    'mime' => $media->mime,
                    'size' => $media->size,
                    'url' => StoreApiSignedUrl::temporary('store-api.v1.returns.media', now()->addMinutes(self::MEDIA_URL_MINUTES), ['return' => $return->id, 'media' => $media->id]),
                ])->all(),
            'refunds' => $return->refunds->map(static fn (Refund $refund): array => [
                'reference' => $refund->reference,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'amount' => $money->present($refund->amount),
                'processed_at' => $refund->processed_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Status changes and customer messages; internal staff notes stay hidden.
     */
    public static function isCustomerVisible(ReturnRequestEvent $event): bool
    {
        return $event->type === ReturnEventType::Status || $event->customer_visible;
    }
}
