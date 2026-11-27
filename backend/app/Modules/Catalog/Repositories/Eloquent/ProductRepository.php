<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\ProductListTab;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\StockState;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

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

    public function findManyForBulk(array $ids): EloquentCollection
    {
        return $this->query()->whereKey($ids)->orderBy('id')->get();
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

    public function findBySku(string $sku): ?array
    {
        $sku = trim($sku);
        $variant = ProductVariant::query()->where('sku', $sku)->whereHas('product')->first(['id', 'product_id']);

        if ($variant !== null) {
            return ['product_id' => (int) $variant->product_id, 'variant_id' => (int) $variant->id];
        }

        $product = $this->query()->where('sku', $sku)->whereDoesntHave('variants')->first(['id']);

        return $product !== null ? ['product_id' => (int) $product->id, 'variant_id' => null] : null;
    }

    public function saveStockTotals(Product $product, int $total, array $variantTotals): void
    {
        foreach ($variantTotals as $variantId => $stock) {
            ProductVariant::query()->whereKey($variantId)->update(['stock' => $stock]);
        }

        $product->forceFill([
            'stock' => $total,
            'out_of_stock_at' => $total > 0 ? null : ($product->out_of_stock_at ?? now()),
        ])->save();
    }

    public function listing(array $filters): Builder
    {
        $tab = ProductListTab::tryFrom((string) ($filters['tab'] ?? '')) ?? ProductListTab::All;
        $status = ProductStatus::tryFrom((string) ($filters['status'] ?? ''));
        $scope = AssignmentScope::tryFrom((string) ($filters['scope'] ?? ''));
        $stock = StockState::tryFrom((string) ($filters['stock'] ?? ''));
        $category = filter_var($filters['category_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        $query = $this->query()->select('products.*');

        match ($tab) {
            ProductListTab::Trash => $query->onlyTrashed(),
            ProductListTab::Published => $query->where('status', ProductStatus::Published),
            ProductListTab::Draft => $query->where('status', ProductStatus::Draft),
            ProductListTab::Archived => $query->where('status', ProductStatus::Archived),
            ProductListTab::OutOfStock => $query->where('manage_stock', true)->where('stock', '<=', 0),
            ProductListTab::All => null,
        };

        return $query
            ->when($status !== null, static fn (Builder $query) => $query->where('status', $status))
            ->when($scope !== null, static fn (Builder $query) => $query->where('assignment_scope', $scope))
            ->when($category !== false && $category !== null, static fn (Builder $query) => $query->whereHas('categories', static fn (Builder $inner) => $inner->whereKey($category)))
            ->when($stock === StockState::InStock, static fn (Builder $query) => $query->whereColumn('stock', '>', 'min_stock')->where('stock', '>', 0))
            ->when($stock === StockState::Low, static fn (Builder $query) => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'))
            ->when($stock === StockState::OutOfStock, static fn (Builder $query) => $query->where('stock', '<=', 0))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower(addcslashes($search, '%_\\')).'%';
                $query->where(static fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(products.sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(products.name, "$.en"))) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(products.name, "$.ar"))) LIKE ?', [$like])
                    ->orWhereHas('variants', static fn (Builder $variant) => $variant->whereRaw('LOWER(sku) LIKE ?', [$like])));
            });
    }

    public function exportRows(array $filters): LazyCollection
    {
        return $this->listing($filters)->with('categories')->orderBy('products.id')->lazyById(500, 'products.id', 'id');
    }

    public function tabCounts(): array
    {
        $counts = ['all' => $this->query()->count()];

        foreach ([ProductListTab::Published, ProductListTab::Draft, ProductListTab::Archived] as $tab) {
            $counts[$tab->value] = $this->query()->where('status', $tab->value)->count();
        }

        $counts[ProductListTab::OutOfStock->value] = $this->query()->where('manage_stock', true)->where('stock', '<=', 0)->count();
        $counts[ProductListTab::Trash->value] = $this->query()->onlyTrashed()->count();

        return $counts;
    }

    public function findWithTrashed(int $id): ?Product
    {
        return $this->query()->withTrashed()->find($id);
    }

    public function restore(Product $product): void
    {
        $product->restore();
    }

    public function sortPage(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $search = $filters['search'] !== null ? trim($filters['search']) : '';

        return $this->query()
            ->with(['media', 'categories'])
            ->when($filters['category_id'] !== null, static fn (Builder $query) => $query->whereHas('categories', static fn (Builder $inner) => $inner->whereKey($filters['category_id'])))
            ->when($filters['country_id'] !== null, static fn (Builder $query) => $query->where(static fn (Builder $inner) => $inner
                ->whereNull('allowed_country_ids')
                ->orWhereJsonContains('allowed_country_ids', $filters['country_id'])))
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $like = '%'.mb_strtolower(addcslashes($search, '%_\\')).'%';
                $query->where(static fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.ar"))) LIKE ?', [$like]));
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    public function renumber(bool $newestFirst = false): void
    {
        $query = $this->query()->withTrashed()->select('id');
        $newestFirst ? $query->orderByDesc('id') : $query->orderBy('sort_order')->orderByDesc('id');
        $position = 0;

        foreach ($query->pluck('id') as $id) {
            $this->query()->withTrashed()->whereKey($id)->update(['sort_order' => $position++]);
        }
    }

    public function hasDuplicateSortOrders(): bool
    {
        return $this->query()->withTrashed()->select('sort_order')->groupBy('sort_order')->havingRaw('COUNT(*) > 1')->exists();
    }

    public function sortOrders(array $ids): array
    {
        /** @var array<int, int> $orders */
        $orders = $this->query()->whereKey($ids)->pluck('sort_order', 'id')->map(static fn (mixed $order): int => (int) $order)->all();

        return $orders;
    }

    public function saveSortOrders(array $orders): void
    {
        foreach ($orders as $id => $order) {
            $this->query()->whereKey($id)->update(['sort_order' => $order]);
        }
    }

    public function nextSortOrder(): int
    {
        $max = $this->query()->withTrashed()->max('sort_order');

        return $max === null ? 0 : (int) $max + 1;
    }

    public function syncCategories(Product $product, array $categoryIds): void
    {
        $current = array_map('intval', $product->categories()->pluck('categories.id')->all());
        $product->categories()->detach(array_values(array_diff($current, $categoryIds)));

        foreach (array_values(array_diff($categoryIds, $current)) as $categoryId) {
            $max = DB::connection($product->getConnectionName())->table('category_product')->where('category_id', $categoryId)->max('sort_order');
            $product->categories()->attach($categoryId, ['sort_order' => $max === null ? 0 : (int) $max + 1]);
        }
    }

    public function syncLabels(Product $product, array $labelIds): void
    {
        $product->labels()->sync($labelIds);
    }

    public function syncTenants(Product $product, array $tenantIds): void
    {
        $product->assignedTenants()->sync($tenantIds);
    }

    public function syncAttributes(Product $product, array $attributeIds): void
    {
        $product->attributes()->sync($attributeIds);
    }

    public function saveVariant(Product $product, ?ProductVariant $variant, array $attributes, array $optionIds): ProductVariant
    {
        if ($variant === null) {
            /** @var ProductVariant $variant */
            $variant = $product->variants()->create($attributes);
        } else {
            $variant->fill($attributes)->save();
        }

        $variant->options()->sync($optionIds);

        return $variant;
    }

    public function deleteVariants(Product $product, array $variantIds): void
    {
        if ($variantIds !== []) {
            $product->variants()->whereKey($variantIds)->get()->each(static function (ProductVariant $variant): void {
                $variant->forceFill(['sku' => null])->save();
                $variant->delete();
            });
        }
    }

    public function variantSkuTaken(string $sku, ?int $ignoreVariantId): bool
    {
        return ProductVariant::query()->where('sku', $sku)->when($ignoreVariantId !== null, static fn (Builder $query) => $query->whereKeyNot($ignoreVariantId))->exists();
    }

    public function createMedia(Product $product, array $attributes): CatalogMedia
    {
        /** @var CatalogMedia $media */
        $media = $product->gallery()->create($attributes);

        return $media;
    }

    public function updateMedia(Product $product, array $changes): void
    {
        foreach ($changes as $id => $attributes) {
            $product->gallery()->whereKey($id)->update($attributes);
        }
    }

    public function pruneMedia(Product $product, array $keepIds): array
    {
        $removed = $product->gallery()->whereKeyNot($keepIds)->get();
        $paths = $removed->flatMap(static fn (CatalogMedia $media): array => $media->paths())->all();
        $product->gallery()->whereKeyNot($keepIds)->delete();

        return $paths;
    }

    public function searchTenants(?string $term, int $page): LengthAwarePaginator
    {
        $term = $term !== null ? trim($term) : '';

        return Tenant::query()
            ->when($term !== '', static fn (Builder $query) => $query->where(static fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('slug', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderBy('name')
            ->paginate(20, ['id', 'name', 'slug'], 'page', max(1, $page));
    }

    public function tenantOptions(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $options */
        $options = Tenant::query()->whereKey($ids)->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();

        return $options;
    }
}
