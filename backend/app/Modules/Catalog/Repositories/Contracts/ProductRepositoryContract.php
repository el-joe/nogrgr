<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Product;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
