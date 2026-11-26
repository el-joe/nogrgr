<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\MarketSelectionData;
use App\Modules\Catalog\DTOs\Vendor\StoreProductUpdateData;
use App\Modules\Catalog\Enums\StoreProductChange;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Catalog\Exceptions\StoreCatalogException;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductMarket;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Vendor-side catalog of imported central products (VEN-010 … 015): import = add
 * store_products rows (idempotent, nothing copied, decision #65), active / featured
 * toggles, removal, sort, store business data (markups, category / badge placement),
 * opportunity markets. Central content changes only through approved edit requests.
 */
class StoreCatalogService extends BaseService
{
    public function __construct(
        private readonly StoreProductRepositoryContract $store,
        private readonly StoreProductPricingService $pricing,
        private readonly CategoryService $categories,
        private readonly ProductBadgeRepositoryContract $badges,
    ) {}

    public static function tenantId(): int
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw new LogicException('The vendor catalog needs an initialized tenant.');
        }

        return (int) $tenant->getTenantKey();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StoreProduct>
     */
    public function listing(array $filters = []): Builder
    {
        return $this->store->centralListing($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function newProducts(array $filters = []): Builder
    {
        return $this->store->newCentral(self::tenantId(), $filters);
    }

    /**
     * Today's chances (VEN-015): offered products ranked by sales then recency.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function opportunities(array $filters = []): Builder
    {
        $query = $this->store->offeredCentral(self::tenantId());
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $categoryId = filter_var($filters['category_id'] ?? null, FILTER_VALIDATE_INT);

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn (Builder $inner) => $inner->where('products.name', 'like', $term)->orWhere('products.sku', 'like', $term));
        }

        if ($categoryId !== false && $categoryId !== null) {
            $query->whereHas('categories', static fn (Builder $categories) => $categories->where('categories.id', $categoryId));
        }

        if (($filters['added'] ?? null) === 'no') {
            $query->whereNotIn('products.id', $this->store->importedCentralIds());
        }

        return $query;
    }

    public function find(int $id): StoreProduct
    {
        return $this->store->findCentral($id) ?? throw (new ModelNotFoundException)->setModel(StoreProduct::class, [$id]);
    }

    public function offeredOrFail(int $centralProductId): Product
    {
        /** @var Product|null $product */
        $product = $this->store->offeredCentral(self::tenantId())->whereKey($centralProductId)->first();

        return $product ?? throw StoreCatalogException::notOffered();
    }

    /**
     * @return list<int>
     */
    public function importedCentralIds(): array
    {
        return $this->store->importedCentralIds();
    }

    public function storeProductFor(int $centralProductId): ?StoreProduct
    {
        return $this->store->findByCentralId($centralProductId);
    }

    /**
     * Imports central products into the store. Already imported products are skipped
     * (idempotent); products not offered to the store are refused.
     *
     * @param  list<int>|null  $centralIds  null = every new offered product
     * @return int number of products added
     */
    public function import(?array $centralIds): int
    {
        $tenantId = self::tenantId();
        $query = $this->store->newCentral($tenantId);

        if ($centralIds !== null) {
            $centralIds = array_values(array_unique(array_map('intval', $centralIds)));
            $offered = $this->store->offeredCentral($tenantId)->whereIn('products.id', $centralIds)->count();

            if ($offered !== count($centralIds)) {
                throw StoreCatalogException::notOffered();
            }

            $query->whereIn('products.id', $centralIds);
        }

        $created = [];

        $this->transaction(function () use ($query, &$created): void {
            $sort = $this->store->nextSortOrder();

            $query->orderBy('products.id')->chunk(200, function ($products) use (&$created, &$sort): void {
                foreach ($products as $central) {
                    /** @var Product $central */
                    $created[] = $this->importOne($central, $sort++)->id;
                }
            });
        });

        if ($created === [] && $centralIds === null) {
            throw StoreCatalogException::nothingToImport();
        }

        if ($created !== []) {
            $this->dispatchAfterCommit(new StoreProductsChanged($tenantId, StoreProductChange::Imported, $created));
        }

        return count($created);
    }

    public function setActive(StoreProduct $product, bool $active): StoreProduct
    {
        $product->forceFill(['is_active' => $active])->save();
        event(new StoreProductsChanged(self::tenantId(), StoreProductChange::Updated, [$product->id]));

        return $product;
    }

    public function setFeatured(StoreProduct $product, bool $featured): StoreProduct
    {
        $product->forceFill(['is_featured' => $featured])->save();
        event(new StoreProductsChanged(self::tenantId(), StoreProductChange::Updated, [$product->id]));

        return $product;
    }

    /**
     * Removes the product from the store (it can be imported again later).
     */
    public function remove(StoreProduct $product): void
    {
        $ids = [$product->id];
        $central = $product->central_product_id !== null ? [$product->central_product_id] : [];
        $product->delete();
        event(new StoreProductsChanged(self::tenantId(), StoreProductChange::Removed, $ids, $central));
    }

    /**
     * @param  list<int>  $ids  every store product id, in display order
     */
    public function sort(array $ids): void
    {
        $known = $this->store->query()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (array_diff($ids, $known) !== [] || count($ids) !== count($known)) {
            throw StoreCatalogException::staleSort();
        }

        $this->store->saveSortOrder($ids);
        event(new StoreProductsChanged(self::tenantId(), StoreProductChange::Updated, $ids));
    }

    /**
     * @return EloquentCollection<int, StoreProduct>
     */
    public function sortable(): EloquentCollection
    {
        return $this->store->sortable();
    }

    /**
     * Saves the store business data of an imported product (VEN-012): markups per country /
     * variant, active / featured, store category and badge placement. Central content is
     * never written here (edit requests only).
     */
    public function update(StoreProduct $product, StoreProductUpdateData $data): StoreProduct
    {
        $this->transaction(function () use ($product, $data): void {
            $product->forceFill([
                'is_active' => $data->isActive,
                'is_featured' => $data->isFeatured,
                'category_ids' => $data->categoryIds === null ? null : $this->validCategoryIds($data->categoryIds),
                'badge_ids' => $data->badgeIds === null ? null : $this->validBadgeIds($data->badgeIds),
                ...($data->storeCategoryIds !== null ? ['store_category_ids' => $data->storeCategoryIds] : []),
            ])->save();
            $this->pricing->saveRows($product, $data->pricing);
        }, $product->getConnectionName());

        $this->dispatchAfterCommit(new StoreProductsChanged(self::tenantId(), StoreProductChange::Updated, [$product->id]));

        return $product->refresh();
    }

    /**
     * Select markets modal (VEN-015a): imports the opportunity when needed and stores its
     * markets with placements. Countries must be store selling countries.
     */
    public function saveMarkets(int $centralProductId, MarketSelectionData $data): StoreProduct
    {
        $central = $this->offeredOrFail($centralProductId);
        $allowed = $this->pricing->countriesFor($central)->pluck('id')->all();

        if ($data->markets === []) {
            throw StoreCatalogException::noMarkets();
        }

        if (array_diff(array_keys($data->markets), $allowed) !== []) {
            throw StoreCatalogException::marketNotSold();
        }

        $created = false;

        $product = $this->transaction(function () use ($central, $data, &$created): StoreProduct {
            $product = $this->store->findByCentralId($central->id);

            if ($product === null) {
                $product = $this->importOne($central, $this->store->nextSortOrder());
                $created = true;
            }

            $this->store->syncMarkets($product, $data->markets);

            return $product;
        });

        $this->dispatchAfterCommit(new StoreProductsChanged(self::tenantId(), $created ? StoreProductChange::Imported : StoreProductChange::Updated, [$product->id]));

        return $product;
    }

    /**
     * Markets of an imported product keyed by country id.
     *
     * @return Collection<int, StoreProductMarket>
     */
    public function markets(?StoreProduct $product): Collection
    {
        return $product === null ? collect() : $product->markets()->get()->keyBy('country_id');
    }

    /**
     * Countries the store may sell a central product in (select markets modal).
     *
     * @return Collection<int, Country>
     */
    public function marketCountries(Product $central): Collection
    {
        return $this->pricing->countriesFor($central);
    }

    /**
     * Category options (central taxonomy) id => path.
     *
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return $this->categories->parentOptions();
    }

    /**
     * @return array<int, string>
     */
    public function badgeOptions(): array
    {
        return $this->badges->allOrdered()
            ->filter(static fn (ProductBadge $badge): bool => $badge->is_active)
            ->mapWithKeys(static fn (ProductBadge $badge): array => [$badge->id => (string) $badge->name])
            ->all();
    }

    /**
     * Adds a central product to the store: one store_products row, nothing copied.
     */
    private function importOne(Product $central, int $sortOrder): StoreProduct
    {
        /** @var StoreProduct $product */
        $product = $this->store->create([
            'source' => StoreProductSource::Central,
            'central_product_id' => $central->id,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'imported_at' => now(),
        ]);

        return $product;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function validCategoryIds(array $ids): array
    {
        return array_values(array_intersect(array_map('intval', $ids), array_keys($this->categoryOptions())));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function validBadgeIds(array $ids): array
    {
        return array_values(array_intersect(array_map('intval', $ids), array_keys($this->badgeOptions())));
    }
}
