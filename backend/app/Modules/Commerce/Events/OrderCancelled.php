<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order was cancelled (customer, store, failed payment or payment timeout). Fired in the store's tenant context
 * after the change is committed; listeners: Reporting order facts, notifications.
 */
final class OrderCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly int $tenantId,
    ) {}
}
