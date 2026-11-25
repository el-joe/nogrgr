<?php

namespace App\Modules\AfterSales\Listeners;

use App\Modules\AfterSales\Enums\ReturnStatus;
use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\AfterSales\Events\RefundFailed;
use App\Modules\AfterSales\Events\ReturnStatusChanged;
use App\Modules\Commerce\Listeners\SendOrderNotifications;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Shared\Money\Money;
use Illuminate\Events\Dispatcher;

/**
 * After-sales events → the shared order notifications (customer e-mail + account inbox,
 * store owners' inbox / push / mail, admins with returns.view / refunds.view):
 *   return opened / every status change / message to the customer;
 *   refund completed (customer: order.refund_processed template) and refund failed
 *   (staff only).
 * Customers and store staff live in the store database: sent in its tenant context.
 */
class SendAfterSalesNotifications
{
    public function __construct(
        private readonly SendOrderNotifications $notify,
        private readonly OrderRepositoryContract $orders,
        private readonly TenantRunner $tenants,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ReturnStatusChanged::class, [self::class, 'returnChanged']);
        $events->listen(RefundCompleted::class, [self::class, 'refundCompleted']);
        $events->listen(RefundFailed::class, [self::class, 'refundFailed']);
    }

    public function returnChanged(ReturnStatusChanged $event): void
    {
        $return = $event->return;
        $status = $event->to;
        $params = ['reference' => $return->reference, 'status' => $status->label(), 'product' => $return->product_name];

        $this->inStore($return->tenant_id, $return->order_uuid, function (Order $order) use ($event, $status, $params): void {
            $customer = $event->from === $status ? 'return_message' : 'return_'.$status->value;
            $this->notify->notifyCustomer($order, null, $customer, [
                'return.reference' => $params['reference'],
                'return.status' => $params['status'],
                'return.message' => (string) $event->message,
            ]);

            if ($event->from === null || $event->from !== $status) {
                $name = $event->from === null ? 'return_opened' : 'return_updated';
                $this->notify->notifyStore($order, 'store_'.$name, $params);

                if ($event->from === null || in_array($status, [ReturnStatus::Approved, ReturnStatus::Rejected, ReturnStatus::Refunded, ReturnStatus::Closed], true)) {
                    $this->notify->notifyAdmins($order, 'admin_'.$name, 'returns.view', $params);
                }
            }
        });
    }

    public function refundCompleted(RefundCompleted $event): void
    {
        $refund = $event->refund;
        $params = ['reference' => $refund->reference, 'amount' => Money::ofMinor($refund->amount, $refund->currency)->format()];

        $this->inStore($refund->tenant_id, $refund->order_uuid, function (Order $order) use ($params): void {
            $this->notify->notifyCustomer($order, EmailTemplateEvent::OrderRefundProcessed, 'refund_processed', [
                'refund.amount' => (string) $params['amount'],
                'refund.reference' => (string) $params['reference'],
            ]);
            $this->notify->notifyStore($order, 'store_refund_completed', $params);
            $this->notify->notifyAdmins($order, 'admin_refund_completed', 'refunds.view', $params);
        });
    }

    public function refundFailed(RefundFailed $event): void
    {
        $refund = $event->refund;
        $params = ['reference' => $refund->reference, 'amount' => Money::ofMinor($refund->amount, $refund->currency)->format(), 'error' => (string) $refund->failure_reason];

        $this->inStore($refund->tenant_id, $refund->order_uuid, function (Order $order) use ($params): void {
            $this->notify->notifyStore($order, 'store_refund_failed', $params);
            $this->notify->notifyAdmins($order, 'admin_refund_failed', 'refunds.view', $params);
        });
    }

    /**
     * @param  callable(Order): void  $callback
     */
    private function inStore(int $tenantId, string $orderUuid, callable $callback): void
    {
        $this->tenants->run($tenantId, function () use ($orderUuid, $callback): void {
            $order = $this->orders->findByUuid($orderUuid);

            if ($order !== null) {
                $callback($order);
            }
        });
    }
}
