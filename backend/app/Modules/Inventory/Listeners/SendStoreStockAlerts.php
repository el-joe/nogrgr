<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Inventory\Events\StoreStockChanged;
use App\Modules\Inventory\Services\Alerts\StockAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Stock of a store-own product changed → low / out of stock alerts and back-in-stock
 * notices of that store (queued, in the store's tenant context).
 */
class SendStoreStockAlerts implements ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public function __construct(
        private readonly TenantRunner $tenants,
        private readonly StockAlertService $alerts,
    ) {}

    public function handle(StoreStockChanged $event): void
    {
        try {
            $this->tenants->run($event->tenantId, fn () => $this->alerts->changed($event->storeProductId, $event->previousStock, $event->currentStock));
        } catch (ModelNotFoundException) {
            // The store was deleted meanwhile.
        }
    }
}
