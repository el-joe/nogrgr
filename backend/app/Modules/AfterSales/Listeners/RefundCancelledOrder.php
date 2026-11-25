<?php

namespace App\Modules\AfterSales\Listeners;

use App\Modules\AfterSales\DTOs\Actor;
use App\Modules\AfterSales\Services\RefundService;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Events\OrderCancelled;

/**
 * A cancelled order whose money was taken online is refunded in full through the
 * gateway (cancellation refund, RefundCalculator::forCancellation). Unpaid / COD orders
 * have nothing to give back.
 */
class RefundCancelledOrder
{
    public function __construct(private readonly RefundService $refunds) {}

    public function handle(OrderCancelled $event): void
    {
        $history = $event->order->history()->latest('id')->first();
        $actor = new Actor($history->actor_type ?? ActorType::System, $history?->actor_id);

        $this->refunds->createForCancellation($event->tenantId, $event->order->loadMissing('items'), $actor);
    }
}
