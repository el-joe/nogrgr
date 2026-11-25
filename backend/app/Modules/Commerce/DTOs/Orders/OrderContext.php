<?php

namespace App\Modules\Commerce\DTOs\Orders;

use App\Modules\Commerce\Models\Order;

/**
 * A store order together with the store it belongs to (admin pages work across stores).
 */
final readonly class OrderContext
{
    public function __construct(
        public int $tenantId,
        public Order $order,
        public ?string $storeName = null,
        public ?string $storeHost = null,
    ) {}
}
