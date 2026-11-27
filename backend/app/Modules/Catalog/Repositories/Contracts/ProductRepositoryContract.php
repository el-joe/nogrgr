<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/**
 * Read side used by the taxonomy screens (the product editor arrives in P3 batch 2).
 *
 * @extends RepositoryContract<Product>
 */
interface ProductRepositoryContract extends RepositoryContract
{
    /**
     * Name / SKU search, excluding the given ids, newest first.
     *
     * @param  list<int>  $excludeIds
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(?string $term, array $excludeIds, int $perPage = 20, int $page = 1): LengthAwarePaginator;

    /**
     * Ids of the products in any of the categories, in the categories' product order.
     *
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function idsInCategories(array $categoryIds): array;

    /**
     * @param  list<int>  $ids
     * @return list<int> the ids that exist
     */
    public function existingIds(array $ids): array;

    /**
     * Products (not trashed) with these ids, for a bulk action.
     *
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Product>
     */
    public function findManyForBulk(array $ids): EloquentCollection;

    /**
     * Published products, newest first.
     *
     * @return list<int>
     */
    public function newestIds(int $limit): array;

    /**
     * Published products by units sold.
     *
     * @return list<int>
     */
    public function topSellingIds(int $limit): array;

    /**
     * Product (no variant) or variant with this SKU.
     *
     * @return array{product_id: int, variant_id: int|null}|null
     */
    public function findBySku(string $sku): ?array;

    /**
     * Writes product / variant stock totals (and the out-of-stock timestamp).
     *
     * @param  array<int, int>  $variantTotals  variant id => stock
     */
    public function saveStockTotals(Product $product, int $total, array $variantTotals): void;

    /**
     * Admin list query (ADM-010): filters tab, search, status, category_id, scope, stock.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function listing(array $filters): Builder;

    /**
     * Rows of the CSV export (same filters as the list).
     *
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, Product>
     */
    public function exportRows(array $filters): LazyCollection;

    /**
     * Counters of the list tabs: tab value => count.
     *
     * @return array<string, int>
     */
    public function tabCounts(): array;

    public function findWithTrashed(int $id): ?Product;

    public function restore(Product $product): void;

    /**
     * Products in storefront order for the sort page.
     *
     * @param  array{category_id: int|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function sortPage(array $filters, int $page, int $perPage): LengthAwarePaginator;

    /**
     * Gives every product a distinct sort_order following the current order (or newest
     * first when $newestFirst).
     */
    public function renumber(bool $newestFirst = false): void;

    public function hasDuplicateSortOrders(): bool;

    /**
     * @param  list<int>  $ids
     * @return array<int, int> id => sort_order
     */
    public function sortOrders(array $ids): array;

    /**
     * @param  array<int, int>  $orders  id => sort_order
     */
    public function saveSortOrders(array $orders): void;

    public function nextSortOrder(): int;

    /**
     * Categories keep their position for already linked ones; new links go last.
     *
     * @param  list<int>  $categoryIds
     */
    public function syncCategories(Product $product, array $categoryIds): void;

    /**
     * @param  list<int>  $labelIds
     */
    public function syncLabels(Product $product, array $labelIds): void;

    /**
     * @param  list<int>  $tenantIds
     */
    public function syncTenants(Product $product, array $tenantIds): void;

    /**
     * @param  list<int>  $attributeIds
     */
    public function syncAttributes(Product $product, array $attributeIds): void;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $optionIds
     */
    public function saveVariant(Product $product, ?ProductVariant $variant, array $attributes, array $optionIds): ProductVariant;

    /**
     * @param  list<int>  $variantIds
     */
    public function deleteVariants(Product $product, array $variantIds): void;

    public function variantSkuTaken(string $sku, ?int $ignoreVariantId): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMedia(Product $product, array $attributes): CatalogMedia;

    /**
     * @param  array<int, array<string, mixed>>  $changes  media id => attributes
     */
    public function updateMedia(Product $product, array $changes): void;

    /**
     * Deletes gallery rows not in the list and returns their stored paths.
     *
     * @param  list<int>  $keepIds
     * @return list<string>
     */
    public function pruneMedia(Product $product, array $keepIds): array;

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function searchTenants(?string $term, int $page): LengthAwarePaginator;

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function tenantOptions(array $ids): array;
}
