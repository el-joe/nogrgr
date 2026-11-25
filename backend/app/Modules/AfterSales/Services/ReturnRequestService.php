<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\DTOs\CreateReturnData;
use App\Modules\AfterSales\DTOs\ReturnActionData;
use App\Modules\AfterSales\Enums\InspectionResult;
use App\Modules\AfterSales\Enums\ReturnAction;
use App\Modules\AfterSales\Enums\ReturnEventType;
use App\Modules\AfterSales\Enums\ReturnMediaKind;
use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Enums\ReturnType;
use App\Modules\AfterSales\Events\ReturnStatusChanged;
use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Models\ReturnRequestMedia;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Pricing\RefundCalculator;
use App\Modules\Commerce\Services\Orders\OrderFinanceService;
use App\Modules\Commerce\Services\Stock\FulfilmentStockService;
use App\Modules\Commerce\Support\TenantRunner;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Returns / exchanges of store orders (ADM-021c, ADM-023a, VEN-022a, decision #69). The
 * one state machine is ReturnStatus; every action is ReturnAction → target status. On
 * opening, the refundable value is fixed with RefundCalculator::forReturn (line value
 * less its coupon share, restocking fee unless the reason is the seller's fault,
 * shipping when the whole order comes back). Inspection may restock the units (central →
 * a branch, own → store stock) and may refund at once; the refund itself is a Refund
 * (RefundService) that moves the return to "refunded" when the money is back.
 */
class ReturnRequestService extends BaseService
{
    public const DISK = 'central';

    public function __construct(
        private readonly ReturnRequestRepositoryContract $returns,
        private readonly ReturnPolicyService $policy,
        private readonly RefundService $refunds,
        private readonly OrderFinanceService $finance,
        private readonly FulfilmentStockService $stock,
        private readonly TenantRunner $tenants,
    ) {}

    public function find(int $id, ?int $tenantId = null): ReturnRequest
    {
        return $this->returns->findForTenant($id, $tenantId) ?? throw AfterSalesException::rule('return_not_found', 404);
    }

    public function order(ReturnRequest $return): Order
    {
        return $this->refunds->order($return->tenant_id, $return->order_uuid);
    }

    public function canOpenFor(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed], true);
    }

    /**
     * Lines that can still be returned: item id => [item, units left].
     *
     * @return array<int, array{item: OrderItem, available: int}>
     */
    public function returnableLines(int $tenantId, Order $order): array
    {
        $lines = [];

        foreach ($order->items as $item) {
            $available = $item->quantity - $this->returns->heldQuantity($tenantId, $order->uuid, $item->id);

            if ($available > 0) {
                $lines[$item->id] = ['item' => $item, 'available' => $available];
            }
        }

        return $lines;
    }

    public function create(int $tenantId, Order $order, CreateReturnData $data, Actor $actor): ReturnRequest
    {
        if (! $this->canOpenFor($order)) {
            throw AfterSalesException::rule('order_not_returnable');
        }

        $line = $this->returnableLines($tenantId, $order)[$data->orderItemId] ?? null;

        if ($line === null) {
            throw AfterSalesException::rule('line_not_returnable');
        }

        if ($data->quantity < 1 || $data->quantity > $line['available']) {
            throw AfterSalesException::rule('quantity_exceeds', 422, ['max' => $line['available']]);
        }

        $reason = $this->policy->reasonByCode($data->reasonCode);
        $policy = $this->policy->policy();

        if ($reason === null || ! $reason->is_active) {
            throw AfterSalesException::rule('reason_invalid');
        }

        if ($data->type === ReturnType::Exchange && ! $policy->allow_exchanges) {
            throw AfterSalesException::rule('exchanges_disabled');
        }

        $item = $line['item'];
        $held = $order->items->sum(fn (OrderItem $other): int => $this->returns->heldQuantity($tenantId, $order->uuid, $other->id));
        $breakdown = RefundCalculator::forReturn(
            order: $this->tenants->run($tenantId, fn () => $this->finance->refundable($order, $tenantId)),
            lineGrandTotal: Money::ofMinor($item->grand_total),
            lineQuantity: $item->quantity,
            quantity: $data->quantity,
            sellerFault: $reason->seller_fault,
            restockingFeeBps: $policy->restocking_fee_bps,
            wholeOrder: $held + $data->quantity >= $order->items->sum('quantity'),
        );

        /** @var ReturnRequest $return */
        $return = $this->transaction(function () use ($tenantId, $order, $data, $actor, $item, $reason, $breakdown): ReturnRequest {
            /** @var ReturnRequest $return */
            $return = $this->returns->create([
                'reference' => $this->returns->nextReference(),
                'tenant_id' => $tenantId,
                'order_uuid' => $order->uuid,
                'order_number' => $order->number,
                'order_item_id' => $item->id,
                'store_product_id' => $item->store_product_id,
                'product_name' => $item->name,
                'variant_label' => $item->variant_label,
                'quantity' => $data->quantity,
                'type' => $data->type,
                'reason_code' => $reason->code,
                'seller_fault' => $reason->seller_fault,
                'description' => $data->description,
                'status' => ReturnStatus::Pending,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer_name,
                'items_amount' => $breakdown->itemsAmount->minor,
                'shipping_amount' => $breakdown->shippingAmount->minor,
                'return_fee' => $breakdown->returnFee->minor,
                'refund_amount' => $breakdown->amount->minor,
                'created_by_type' => $actor->type,
                'created_by_id' => $actor->id,
            ]);

            $this->returns->addEvent($return, ['type' => ReturnEventType::Status, 'to_status' => ReturnStatus::Pending, 'note' => $data->description, 'actor_type' => $actor->type, 'actor_id' => $actor->id, 'actor_name' => $actor->name]);

            return $return;
        }, $this->connection());

        ReturnStatusChanged::dispatch($return, null, ReturnStatus::Pending);

        return $return;
    }

    public function act(ReturnRequest $return, ReturnActionData $data, Actor $actor): ReturnRequest
    {
        $from = $return->status;
        $target = $data->action->target($from);

        if (! in_array($data->action, ReturnAction::availableFor($from, $return->type), true)) {
            throw AfterSalesException::rule('transition_not_allowed', 422, ['action' => $data->action->label(), 'status' => $from->label()]);
        }

        if (in_array($data->action, [ReturnAction::Reject, ReturnAction::RequestInfo], true) && trim((string) $data->note) === '') {
            throw AfterSalesException::rule('note_required');
        }

        return match ($data->action) {
            ReturnAction::Inspect => $this->inspect($return, $data, $actor),
            ReturnAction::Refund => $this->refund($return, $data->amount, $actor),
            default => $this->move($return, $target, $actor, $data->note, $data->action === ReturnAction::RequestInfo, $this->stamps($data)),
        };
    }

    public function addNote(ReturnRequest $return, string $note, bool $customerVisible, Actor $actor): void
    {
        $this->returns->addEvent($return, [
            'type' => $customerVisible ? ReturnEventType::Message : ReturnEventType::Note,
            'note' => $note,
            'customer_visible' => $customerVisible,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
        ]);

        if ($customerVisible) {
            ReturnStatusChanged::dispatch($return, $return->status, $return->status, $note);
        }
    }

    /**
     * The customer withdraws a request still under review (pending / awaiting info).
     */
    public function withdraw(ReturnRequest $return, Actor $actor, ?string $note = null): ReturnRequest
    {
        if (! $return->status->isWithdrawable()) {
            throw AfterSalesException::rule('withdraw_not_allowed', 422, ['status' => $return->status->label()]);
        }

        return $this->move($return, ReturnStatus::Cancelled, $actor, $note, true, ['cancelled_at' => now()]);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function addPhotos(ReturnRequest $return, array $files, ReturnMediaKind $kind, Actor $actor): void
    {
        foreach ($files as $file) {
            $path = $file->store('returns/'.$return->tenant_id.'/'.$return->id, self::DISK);

            if ($path === false) {
                throw AfterSalesException::rule('upload_failed');
            }

            $this->returns->addMedia($return, [
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'kind' => $kind,
                'uploaded_by_type' => $actor->type,
                'uploaded_by_id' => $actor->id,
            ]);
        }
    }

    public function media(ReturnRequest $return, int $mediaId): ReturnRequestMedia
    {
        $media = $this->returns->findMedia($return, $mediaId);

        if ($media === null || ! Storage::disk($media->disk)->exists($media->path)) {
            throw AfterSalesException::rule('file_not_found', 404);
        }

        return $media;
    }

    /**
     * Largest refund the return still allows (its fixed value less the refunds already
     * raised for it, capped by the order's refundable remainder; RefundService::maxForReturn).
     */
    public function maxRefund(ReturnRequest $return, ?Order $order = null): Money
    {
        $order ??= $this->order($return);

        return $this->refunds->maxForReturn($return, $order);
    }

    private function inspect(ReturnRequest $return, ReturnActionData $data, Actor $actor): ReturnRequest
    {
        if ($data->result === null) {
            throw AfterSalesException::rule('inspection_result_required');
        }

        $extra = [
            'inspection_result' => $data->result,
            'inspection_condition' => $data->condition,
            'inspection_notes' => $data->note,
            'inspected_at' => now(),
        ];

        if ($data->result === InspectionResult::Accepted && $data->restock) {
            $order = $this->order($return);
            $item = $order->items->firstWhere('id', $return->order_item_id);

            if ($item instanceof OrderItem) {
                $branch = $this->tenants->run($return->tenant_id, fn (): ?int => $this->stock->restockLine($order, $item, $return->quantity, $data->branchId));
                $extra += ['restocked' => true, 'restock_branch_id' => $branch, 'restocked_at' => now()];
            }
        }

        if ($data->photos !== []) {
            $this->addPhotos($return, $data->photos, ReturnMediaKind::Inspection, $actor);
        }

        $this->move($return, ReturnStatus::Inspected, $actor, $data->note, false, $extra);

        if ($data->result === InspectionResult::Failed) {
            return $this->move($return, ReturnStatus::Rejected, $actor, $data->note ?: (string) __('after-sales::returns.notes.inspection_failed'), true);
        }

        if ($data->thenRefund && $return->type === ReturnType::Return) {
            return $this->refund($return, $data->amount ?? $this->maxRefund($return), $actor);
        }

        return $return;
    }

    private function refund(ReturnRequest $return, ?Money $amount, Actor $actor): ReturnRequest
    {
        $order = $this->order($return);
        $amount ??= $this->maxRefund($return, $order);
        $this->refunds->createForReturn($return, $order, $amount, $actor);

        return $return->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(ReturnRequest $return, ReturnStatus $to, Actor $actor, ?string $note, bool $customerVisible, array $extra = []): ReturnRequest
    {
        $from = $return->status;

        if (! $from->canTransitionTo($to)) {
            throw AfterSalesException::rule('transition_not_allowed', 422, ['action' => $to->label(), 'status' => $from->label()]);
        }

        $this->transaction(function () use ($return, $from, $to, $actor, $note, $customerVisible, $extra): void {
            $this->returns->setStatus($return, $to, $extra);
            $this->returns->addEvent($return, [
                'type' => ReturnEventType::Status,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'customer_visible' => $customerVisible,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ]);
        }, $this->connection());

        ReturnStatusChanged::dispatch($return, $from, $to, $customerVisible ? $note : null);

        return $return;
    }

    /**
     * @return array<string, mixed>
     */
    private function stamps(ReturnActionData $data): array
    {
        return match ($data->action) {
            ReturnAction::Approve => ['approved_at' => now()],
            ReturnAction::MarkReceived => ['received_at' => now()],
            ReturnAction::ShipExchange => ['exchange_shipped_at' => now(), 'exchange_tracking_number' => $data->trackingNumber],
            ReturnAction::CompleteExchange => ['exchanged_at' => now()],
            ReturnAction::Close => ['closed_at' => now()],
            ReturnAction::Reject => ['cancelled_at' => null],
            default => [],
        };
    }

    private function connection(): ?string
    {
        return (new ReturnRequest)->getConnectionName();
    }
}
