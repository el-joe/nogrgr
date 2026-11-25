<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\OwnProductSection;
use App\Modules\Catalog\Enums\StoreProductFilter;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductMarket;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * @extends BaseRepository<StoreProduct>
 */
class StoreProductRepository extends BaseRepository implements StoreProductRepositoryContract
{
    protected function model(): string
    {
        return StoreProduct::class;
    }

    public function centralListing(array $filters = []): Builder
    {
        $query = $this->query()->central();
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $categoryId = filter_var($filters['category_id'] ?? null, FILTER_VALIDATE_INT);

        if ($search !== '') {
            $query->whereIn('central_product_id', $this->centralIdsMatching($search));
        }

        if ($categoryId !== false && $categoryId !== null) {
            $centralIds = Product::query()->whereHas('categories', static fn (Builder $categories) => $categories->where('categories.id', $categoryId))->pluck('products.id')->all();
            $query->where(static function (Builder $inner) use ($categoryId, $centralIds): void {
                $inner->whereJsonContains('category_ids', (int) $categoryId)
                    ->orWhere(static fn (Builder $central) => $central->whereNull('category_ids')->whereIn('central_product_id', $centralIds));
            });
        }

        return $this->applyStatus($query, $filters);
    }

    public function ownListing(array $filters = []): Builder
    {
        $query = $this->query()->own();
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn (Builder $inner) => $inner->where('name', 'like', $term)->orWhere('sku', 'like', $term));
        }

        $section = OwnProductSection::tryFrom((string) ($filters['section'] ?? ''));

        if ($section !== null) {
            $this->applySection($query, $section);
        }

        return $this->applyStatus($query, $filters);
    }

    public function ownSection(OwnProductSection $section, int $limit): Collection
    {
        return $this->applySection($this->query()->own()->with('media'), $section)->limit($limit)->get();
    }

    public function findCentral(int $id): ?StoreProduct
    {
        return $this->query()->central()->find($id);
    }

    public function findOwn(int $id): ?StoreProduct
    {
        return $this->query()->own()->find($id);
    }

    public function findByCentralId(int $centralProductId): ?StoreProduct
    {
        return $this->query()->where('central_product_id', $centralProductId)->first();
    }

    public function importedCentralIds(): array
    {
        return $this->query()->central()->pluck('central_product_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public function offeredCentral(int $tenantId): Builder
    {
        return Product::query()->published()->offeredTo($tenantId);
    }

    public function newCentral(int $tenantId, array $filters = []): Builder
    {
        $query = $this->offeredCentral($tenantId)->whereNotIn('products.id', $this->importedCentralIds());
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $categoryId = filter_var($filters['category_id'] ?? null, FILTER_VALIDATE_INT);

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn (Builder $inner) => $inner->where('products.name', 'like', $term)->orWhere('products.sku', 'like', $term));
        }

        if ($categoryId !== false && $categoryId !== null) {
            $query->whereHas('categories', static fn (Builder $categories) => $categories->where('categories.id', $categoryId));
        }

        return $query;
    }

    public function nextSortOrder(): int
    {
        return (int) $this->query()->max('sort_order') + 1;
    }

    public function saveSortOrder(array $ids): void
    {
        DB::connection($this->query()->getModel()->getConnectionName())->transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                $this->query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function sortable(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderByDesc('id')->get();
    }

    public function syncMarkets(StoreProduct $product, array $markets): void
    {
        StoreProductMarket::query()->where('store_product_id', $product->id)->whereNotIn('country_id', array_keys($markets))->delete();

        foreach ($markets as $countryId => $placements) {
            StoreProductMarket::query()->updateOrCreate(
                ['store_product_id' => $product->id, 'country_id' => $countryId],
                ['placements' => array_values(array_unique($placements))],
            );
        }
    }

    public function exportRows(array $filters, array $with = []): LazyCollection
    {
        return $this->centralListing($filters)->with($with)->orderBy('sort_order')->orderByDesc('id')->lazyById(200);
    }

    public function centralIdsMatching(string $term): array
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return Product::query()
            ->where(static fn (Builder $inner) => $inner->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->limit(2000)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Builder<StoreProduct>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<StoreProduct>
     */
    private function applyStatus(Builder $query, array $filters): Builder
    {
        return match (StoreProductFilter::tryFrom((string) ($filters['status'] ?? ''))) {
            StoreProductFilter::Active => $query->where('is_active', true),
            StoreProductFilter::Inactive => $query->where('is_active', false),
            StoreProductFilter::Featured => $query->where('is_featured', true),
            StoreProductFilter::OutOfStock => $query->whereNotNull('stock')->where('stock', '<=', 0),
            null => $query,
        };
    }

    /**
     * @param  Builder<StoreProduct>  $query
     * @return Builder<StoreProduct>
     */
    private function applySection(Builder $query, OwnProductSection $section): Builder
    {
        return match ($section) {
            OwnProductSection::NewIn => $query->latest('created_at')->orderByDesc('id'),
            OwnProductSection::Featured => $query->where('is_featured', true)->orderBy('sort_order')->orderByDesc('id'),
            OwnProductSection::BestSellers => $query->where('sold_count', '>', 0)->orderByDesc('sold_count')->orderByDesc('id'),
        };
    }

    public function hasAny(): bool
    {
        return $this->query()->exists();
    }
}
