<?php

namespace App\Modules\Shipping\Repositories\Eloquent;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shipping\Repositories\Contracts\ProductShippingMapRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Product>
 */
class ProductShippingMapRepository extends BaseRepository implements ProductShippingMapRepositoryContract
{
    protected function model(): string
    {
        return Product::class;
    }

    public function chunkWeights(?array $productIds, int $size, Closure $callback): void
    {
        foreach (['products' => Product::query()->withTrashed(), 'product_variants' => ProductVariant::query()->withTrashed()] as $table => $query) {
            /** @var Builder<Product>|Builder<ProductVariant> $query */
            $query
                ->when($productIds !== null, static fn (Builder $query) => $query->whereIn($table === 'products' ? 'id' : 'product_id', $productIds))
                ->select(['id', 'weight_grams'])
                ->chunkById($size, static function ($rows) use ($table, $callback): void {
                    $callback($table, $rows->mapWithKeys(static fn ($row): array => [(int) $row->id => $row->weight_grams === null ? null : (int) $row->weight_grams])->all());
                });
        }
    }

    public function saveMap(string $table, int $id, ?array $map): void
    {
        $query = $table === 'products' ? Product::query()->withTrashed() : ProductVariant::query()->withTrashed();
        $query->whereKey($id)->toBase()->update(['fixed_shipping_costs' => $map === null ? null : json_encode($map, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)]);
    }
}
