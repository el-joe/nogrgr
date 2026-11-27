<?php

namespace App\Modules\Inventory\Jobs;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Inventory\Services\Alerts\StockAlertService;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the stock alerts of a central product in every active store that imported it
 * (store_products.central_product_id): the team's low / out of stock alert with the
 * store's own threshold, and its back-in-stock subscribers.
 */
class FanOutCentralStockAlerts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $productId,
        public readonly int $previousStock,
        public readonly int $currentStock,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(StockAlertService $alerts): void
    {
        Tenant::query()->where('status', TenantStatus::Active->value)->orderBy('id')->each(function (Tenant $tenant) use ($alerts): void {
            try {
                $tenant->run(function () use ($alerts): void {
                    $ids = StoreProduct::query()->where('central_product_id', $this->productId)->pluck('id');

                    foreach ($ids as $id) {
                        $alerts->changed((int) $id, $this->previousStock, $this->currentStock);
                    }
                });
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
