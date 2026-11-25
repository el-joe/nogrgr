<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Central stock of a product changed (product form, branch import). Listeners: tenant
 * stock sync and back-in-stock notifications (later phases).
 */
final class StockChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $productId,
        public readonly int $totalStock,
        public readonly ?int $branchId = null,
    ) {}
}
