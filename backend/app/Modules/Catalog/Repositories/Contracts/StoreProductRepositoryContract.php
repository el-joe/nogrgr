<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Enums\OwnProductSection;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StoreProduct;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Store catalog of the current tenant (tenant DB `store_products` + children) and the
 * central products offered to it (central DB, read only).
 *
 * @extends RepositoryContract<StoreProduct>
 */
interface StoreProductRepositoryContract extends RepositoryContract
{
    /**
     * Imported central products. Filters: search (central name / sku), status (StoreProductFilter), category_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<StoreProduct>
     */
    public function centralListing(array $filters = []): Builder;

    /**
     * Own products. Filters: search, status, section (OwnProductSection).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<StoreProduct>
     */
    public function ownListing(array $filters = []): Builder;

    /**
     * @return Collection<int, StoreProduct>
     */
    public function ownSection(OwnProductSection $section, int $limit): Collection;

    public function findCentral(int $id): ?StoreProduct;

    public function findOwn(int $id): ?StoreProduct;

    public function findByCentralId(int $centralProductId): ?StoreProduct;

    /**
     * Central product ids already in the store.
     *
     * @return list<int>
     */
    public function importedCentralIds(): array;

    /**
     * Central products offered to the tenant (published, scope all or assigned).
     *
     * @return Builder<Product>
     */
    public function offeredCentral(int $tenantId): Builder;

    /**
     * Offered central products not in the store yet. Filters: search, category_id.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function newCentral(int $tenantId, array $filters = []): Builder;

    public function nextSortOrder(): int;

    /**
     * @param  list<int>  $ids  in display order
     */
    public function saveSortOrder(array $ids): void;

    /**
     * @return Collection<int, StoreProduct>
     */
    public function sortable(): Collection;

    /**
     * @param  array<int, list<string>>  $markets  country id => placements
     */
    public function syncMarkets(StoreProduct $product, array $markets): void;

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<array-key, mixed>  $with  eager loads applied per chunk
     * @return LazyCollection<int, StoreProduct>
     */
    public function exportRows(array $filters, array $with = []): LazyCollection;

    /**
     * Central ids matching a name / sku search (for searches over tenant rows).
     *
     * @return list<int>
     */
    public function centralIdsMatching(string $term): array;

    /**
     * Whether the store sells at least one product (imported central or own).
     */
    public function hasAny(): bool;
}
