<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A shipping step that did not change the order status (out for delivery, delayed,
 * carrier / tracking changes): order facts resync, the customer is told when asked.
 */
final class OrderFulfilmentUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly int $tenantId,
        public readonly FulfilmentStatus $status,
        public readonly bool $notifyCustomer = true,
    ) {}
}
