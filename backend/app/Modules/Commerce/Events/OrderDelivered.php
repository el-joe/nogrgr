<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order was delivered to the customer. Fired in the store's tenant context
 * after the change is committed; listeners: Reporting order facts, notifications.
 */
final class OrderDelivered
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly int $tenantId,
    ) {}
}
