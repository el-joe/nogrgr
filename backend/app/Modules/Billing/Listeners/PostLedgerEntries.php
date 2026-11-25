<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\AfterSales\Events\RefundCompleted;
use App\Modules\Billing\Enums\LedgerSource;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Services\Ledger\FinancePostings;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Commerce\Events\SettlementPaid;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Support\TenantRunner;
use Illuminate\Events\Dispatcher;

/**
 * Feeds the central ledger from domain events (decision #72). Every posting is keyed by
 * its source event, so a replayed event changes nothing.
 */
class PostLedgerEntries
{
    public function __construct(
        private readonly FinancePostings $postings,
        private readonly OrderRepositoryContract $orders,
        private readonly TenantRunner $tenants,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderPaid::class, [self::class, 'orderPaid']);
        $events->listen(OrderStatusChanged::class, [self::class, 'orderStatusChanged']);
        $events->listen(RefundCompleted::class, [self::class, 'refundCompleted']);
        $events->listen(SettlementPaid::class, [self::class, 'settlementPaid']);
        $events->listen(InvoicePaid::class, [self::class, 'invoicePaid']);
    }

    public function orderPaid(OrderPaid $event): void
    {
        $this->postings->order($event->tenantId, $event->order, 'order:'.$event->tenantId.':'.$event->order->uuid.':paid');
    }

    public function orderStatusChanged(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Returned], true)) {
            return;
        }

        $this->postings->order($event->tenantId, $event->order, 'order:'.$event->tenantId.':'.$event->order->uuid.':'.$event->to->value);
    }

    public function refundCompleted(RefundCompleted $event): void
    {
        $refund = $event->refund;
        $order = $this->tenants->run($refund->tenant_id, fn () => $this->orders->findByUuid($refund->order_uuid));

        if ($order !== null) {
            $this->postings->order($refund->tenant_id, $order, 'refund:'.$refund->id, LedgerSource::Refund, $refund->reference);
        }
    }

    public function settlementPaid(SettlementPaid $event): void
    {
        $this->postings->settlement($event->settlement);
    }

    public function invoicePaid(InvoicePaid $event): void
    {
        $this->postings->invoicePaid($event->invoice);
    }
}
