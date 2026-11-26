<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreProduct>
 */
class StorefrontCatalogRepository extends BaseRepository implements StorefrontCatalogRepositoryContract
{
    protected function model(): string
    {
        return StoreProduct::class;
    }

    /**
     * Orders that never turned into a sale.
     */
    private const UNSOLD = [OrderStatus::PendingPayment, OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Returned];

    public function eachActiveChunk(int $size, callable $callback): void
    {
        StoreProduct::query()
            ->where('is_active', true)
            ->with(StoreCatalogReader::relations())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->chunk($size, static function (Collection $products) use ($callback): void {
                $callback($products);
            });
    }

    public function findActive(int $id): ?StoreProduct
    {
        return StoreProduct::query()->whereKey($id)->where('is_active', true)->with(StoreCatalogReader::relations())->first();
    }

    public function unitsSoldSince(CarbonInterface $since): array
    {
        return OrderItem::query()
            ->selectRaw('order_items.store_product_id, SUM(order_items.quantity) as units')
            ->whereHas('order', static fn (Builder $query) => $query
                ->where('placed_at', '>=', $since)
                ->whereNotIn('status', array_map(static fn (OrderStatus $status): string => $status->value, self::UNSOLD)))
            ->groupBy('order_items.store_product_id')
            ->pluck('units', 'store_product_id')
            ->map(static fn (mixed $units): int => (int) $units)
            ->all();
    }

    public function activeCategories(): Collection
    {
        return Category::query()
            ->where('status', RecordStatus::Active)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
