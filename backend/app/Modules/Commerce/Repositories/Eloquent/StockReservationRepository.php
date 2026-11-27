<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Commerce\Enums\StockReservationStatus;
use App\Modules\Commerce\Models\StockReservation;
use App\Modules\Commerce\Repositories\Contracts\StockReservationRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use App\Shared\Tenancy\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<StockReservation>
 */
class StockReservationRepository extends BaseRepository implements StockReservationRepositoryContract
{
    protected function model(): string
    {
        return StockReservation::class;
    }

    public function heldUnits(StoreProductSource $source, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $query = $source === StoreProductSource::Central
            ? StockReservation::withoutTenantScope()
            : $this->query();

        $rows = $query
            ->where('product_source', $source->value)
            ->whereIn('product_id', $productIds)
            ->where(static fn (Builder $held) => $held
                ->where('status', StockReservationStatus::Committed->value)
                ->orWhere(static fn (Builder $live) => $live
                    ->where('status', StockReservationStatus::Reserved->value)
                    ->where(static fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()))))
            ->selectRaw('product_id, variant_key, SUM(quantity) as units')
            ->groupBy('product_id', 'variant_key')
            ->get();

        $held = [];

        foreach ($rows as $row) {
            $held[$row->product_id.':'.$row->variant_key] = (int) $row->getAttribute('units');
        }

        return $held;
    }

    public function lockProducts(StoreProductSource $source, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $model = $source === StoreProductSource::Central ? Product::class : StoreProduct::class;
        $model::query()->withoutGlobalScopes()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->pluck('id');
    }

    public function forGroup(string $checkoutGroup, ?StockReservationStatus $status = null): Collection
    {
        return $this->query()
            ->withoutGlobalScope(CurrentTenantScope::class)
            ->where('checkout_group', $checkoutGroup)
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value))
            ->orderBy('id')
            ->get();
    }

    public function forOrder(string $orderUuid, ?StockReservationStatus $status = null): Collection
    {
        return $this->query()
            ->withoutGlobalScope(CurrentTenantScope::class)
            ->where('order_uuid', $orderUuid)
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status?->value))
            ->orderBy('id')
            ->get();
    }

    public function incrementOwnStock(int $storeProductId, int $variantKey, int $quantity): void
    {
        $quantity = max(0, $quantity);

        if ($variantKey > 0) {
            StoreProductVariant::query()->whereKey($variantKey)->whereNotNull('stock')->increment('stock', $quantity);
        }

        StoreProduct::query()->whereKey($storeProductId)->where('manage_stock', true)->increment('stock', $quantity);
    }

    public function setStatus(StockReservation $reservation, StockReservationStatus $status): void
    {
        $reservation->forceFill(['status' => $status])->save();
    }

    public function connectionName(StoreProductSource $source): ?string
    {
        return $source === StoreProductSource::Central ? (new Product)->getConnectionName() : (new StoreProduct)->getConnectionName();
    }

    public function stockLevels(StoreProductSource $source, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $levels = [];

        if ($source === StoreProductSource::Central) {
            $products = Product::query()->withoutGlobalScopes()->whereIn('id', $productIds)->get(['id', 'stock', 'manage_stock']);

            foreach ($products as $product) {
                $levels[$product->id.':0'] = $product->manage_stock ? (int) $product->stock : null;
            }

            foreach (ProductVariant::query()->whereIn('product_id', $productIds)->get(['id', 'product_id', 'stock']) as $variant) {
                $levels[$variant->product_id.':'.$variant->id] = ($levels[$variant->product_id.':0'] ?? null) === null ? null : (int) $variant->stock;
            }

            return $levels;
        }

        $products = StoreProduct::query()->whereIn('id', $productIds)->get(['id', 'stock', 'manage_stock']);

        foreach ($products as $product) {
            $levels[$product->id.':0'] = $product->manage_stock && $product->stock !== null ? (int) $product->stock : null;
        }

        foreach (StoreProductVariant::query()->whereIn('store_product_id', $productIds)->get(['id', 'store_product_id', 'stock']) as $variant) {
            $productLevel = $levels[$variant->store_product_id.':0'] ?? null;
            $levels[$variant->store_product_id.':'.$variant->id] = $productLevel === null ? null : ($variant->stock ?? $productLevel);
        }

        return $levels;
    }

    public function ownStock(int $storeProductId): ?int
    {
        $row = StoreProduct::query()->whereKey($storeProductId)->first(['id', 'stock', 'manage_stock']);

        return $row !== null && $row->manage_stock ? (int) $row->stock : null;
    }

    public function decrementOwnStock(int $storeProductId, int $variantKey, int $quantity): void
    {
        if ($variantKey > 0) {
            StoreProductVariant::query()->whereKey($variantKey)->whereNotNull('stock')->update(['stock' => DB::raw(self::minusClamped('stock', $quantity))]);
        }

        StoreProduct::query()->whereKey($storeProductId)->where('manage_stock', true)->update(['stock' => DB::raw(self::minusClamped('stock', $quantity))]);
    }

    /**
     * Portable "max(column − n, 0)" (MySQL and the SQLite CI leg).
     */
    private static function minusClamped(string $column, int $quantity): string
    {
        $quantity = max(0, $quantity);

        return "CASE WHEN COALESCE({$column}, 0) > {$quantity} THEN COALESCE({$column}, 0) - {$quantity} ELSE 0 END";
    }

    public function expiredGroupsByTenant(int $limit = 500): array
    {
        $groups = [];

        $rows = StockReservation::withoutTenantScope()
            ->where('status', StockReservationStatus::Reserved->value)
            ->where('expires_at', '<', now())
            ->limit($limit)
            ->get(['tenant_id', 'checkout_group']);

        foreach ($rows as $row) {
            $groups[$row->tenant_id][$row->checkout_group] = $row->checkout_group;
        }

        return array_map('array_values', $groups);
    }
}
