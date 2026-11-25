<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository implements ProductRepositoryContract
{
    protected function model(): string
    {
        return Product::class;
    }

    public function search(?string $term, array $excludeIds, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $term = $term !== null ? trim($term) : '';

        return $this->query()
            ->with(['media', 'categories'])
            ->when($excludeIds !== [], static fn (Builder $query) => $query->whereKeyNot($excludeIds))
            ->when($term !== '', static function (Builder $query) use ($term): void {
                $like = '%'.mb_strtolower(addcslashes($term, '%_\\')).'%';
                $query->where(static fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`name`, "$.en"))) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`name`, "$.ar"))) LIKE ?', [$like]));
            })
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    public function idsInCategories(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $this->query()
            ->join('category_product', 'category_product.product_id', '=', 'products.id')
            ->whereIn('category_product.category_id', $categoryIds)
            ->orderBy('category_product.sort_order')
            ->orderBy('products.id')
            ->pluck('products.id')
            ->all())));
    }

    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map('intval', $this->query()->whereKey($ids)->pluck('id')->all());
    }

    public function newestIds(int $limit): array
    {
        return array_map('intval', $this->query()->published()->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->pluck('id')->all());
    }

    public function topSellingIds(int $limit): array
    {
        return array_map('intval', $this->query()->published()->where('sold_count', '>', 0)->orderByDesc('sold_count')->orderByDesc('id')->limit($limit)->pluck('id')->all());
    }
}
