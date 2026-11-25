<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\DTOs\Orders\CancelOrderData;
use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Shared\Services\BaseService;

/**
 * Staff cancellation (admin ADM-021a, vendor VEN-021a): only before the parcel leaves
 * (pending payment / paid / processing and not in transit), with a reason code. The stock
 * holds are released by the lifecycle; a paid order is refunded in full by AfterSales
 * (listener on OrderCancelled → cancellation refund through the gateway).
 */
class OrderCancellationService extends BaseService
{
    public function __construct(private readonly OrderLifecycleService $lifecycle) {}

    public function canCancel(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing], true)
            && in_array($order->fulfilment_status, [FulfilmentStatus::Pending, FulfilmentStatus::Packed], true);
    }

    public function cancel(Order $order, CancelOrderData $data): Order
    {
        if (! $this->canCancel($order)) {
            throw CommerceException::rule('not_cancellable');
        }

        if ($data->reason->requiresNote() && trim((string) $data->note) === '') {
            throw CommerceException::rule('cancel_note_required');
        }

        $order->forceFill(['cancel_reason_code' => $data->reason]);

        return $this->lifecycle->cancel($order, $data->actor, $data->actorId, mb_substr($data->historyNote(), 0, 500));
    }
}
