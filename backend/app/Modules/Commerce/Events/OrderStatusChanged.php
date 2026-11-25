<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Any status change of an order (state machine), fired with the specific event when
 * there is one. Keeps the central order_facts row in sync.
 */
final class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly int $tenantId,
        public readonly ?OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}
}
