<?php

namespace App\Modules\Inventory\Services\Alerts;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Inventory\Enums\StockAlertEvent;
use App\Modules\Inventory\Notifications\StockAlertNotification;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Notification;

/**
 * Stock alerts of a store (tenant context, decision #124):
 *   threshold = the store product's own minimum (min_stock) when set, else the central
 *               product's minimum (imported products), else the store setting
 *               "low_stock_threshold" (Vendor › Settings › Notifications, default 5);
 *   out of stock (reaches 0) / low stock (crosses the threshold downwards) → owners +
 *   active members with catalog.products.view (db, Reverb, mail — mail off by default
 *   for the low_stock topic, VEN-087);
 *   back from 0 → the back-in-stock subscribers (BackInStockService, sent once).
 * Products that do not track stock never alert.
 */
class StockAlertService extends BaseService
{
    public const DEFAULT_THRESHOLD = 5;

    public const MAX_THRESHOLD = 100000;

    public function __construct(
        private readonly StoreSettingRepositoryContract $settings,
        private readonly StoreCatalogReader $reader,
        private readonly VendorStaffRepositoryContract $staff,
        private readonly BackInStockService $backInStock,
    ) {}

    public function storeThreshold(): int
    {
        $value = $this->settings->get(StoreSettingKey::LowStockThreshold);

        return is_numeric($value) ? max(0, (int) $value) : self::DEFAULT_THRESHOLD;
    }

    public function setStoreThreshold(int $threshold): void
    {
        $this->settings->put(StoreSettingKey::LowStockThreshold, max(0, min(self::MAX_THRESHOLD, $threshold)));
    }

    public function threshold(StoreProductView $view): int
    {
        $own = (int) ($view->product->min_stock ?? 0);

        if ($own > 0) {
            return $own;
        }

        $central = (int) ($view->central?->min_stock ?? 0);

        return $central > 0 ? $central : $this->storeThreshold();
    }

    public function changed(int $storeProductId, int $previous, int $current): void
    {
        if ($previous === $current) {
            return;
        }

        /** @var StoreProduct|null $product */
        $product = StoreProduct::query()->find($storeProductId);

        if ($product === null) {
            return;
        }

        $view = $this->reader->view($this->reader->load($product));

        if (! $this->tracksStock($view)) {
            return;
        }

        $alert = StockAlertEvent::forChange($previous, $current, $this->threshold($view));

        if ($alert !== null && $product->is_active) {
            $this->alertTeam($view, $alert, $current);
        }

        if (StockAlertEvent::isRestock($previous, $current)) {
            $this->backInStock->notifySubscribers($view);
        }
    }

    private function alertTeam(StoreProductView $view, StockAlertEvent $alert, int $current): void
    {
        $recipients = $this->staff->activeOwners()->merge($this->staff->activeWithPermission('catalog.products.view'))->unique('id')->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $tenant = tenant();
        $route = $view->isOwn() ? 'catalog.own-products.inventory' : 'catalog.products.index';
        $link = function_exists('vendor_route') ? rescue(static fn (): string => vendor_route($route), null, false) : null;

        Notification::send($recipients, new StockAlertNotification($alert, [
            'product' => $view->name,
            'stock' => $current,
            'threshold' => $this->threshold($view),
            'store' => $tenant !== null ? (string) $tenant->getAttribute('name') : '',
        ], $link));
    }

    private function tracksStock(StoreProductView $view): bool
    {
        if ($view->product->source === StoreProductSource::Own) {
            return (bool) $view->product->manage_stock;
        }

        return (bool) ($view->central?->manage_stock ?? true);
    }
}
