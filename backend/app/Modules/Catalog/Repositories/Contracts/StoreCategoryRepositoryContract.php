<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreCentralCategory;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store taxonomy (tenant DB, decision #74): store-own categories, the store's settings of
 * central categories and the product order inside a category.
 *
 * @extends RepositoryContract<StoreCategory>
 */
interface StoreCategoryRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, StoreCategory>
     */
    public function allOrdered(): Collection;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    public function nextSortOrder(): int;

    /**
     * Ids of the category and every store category below it.
     *
     * @return list<int>
     */
    public function descendantIds(int $id): array;

    /**
     * central category id => the store's setting row.
     *
     * @return array<int, StoreCentralCategory>
     */
    public function centralSettings(): array;

    /**
     * @param  array{is_hidden?: bool, sort_order?: int|null}  $attributes
     */
    public function saveCentralSetting(int $centralCategoryId, array $attributes): StoreCentralCategory;

    /**
     * Product order inside a category: store product id => position.
     *
     * @return array<int, int>
     */
    public function positions(string $categoryRef): array;

    /**
     * Replaces the product order of a category.
     *
     * @param  list<int>  $storeProductIds
     */
    public function savePositions(string $categoryRef, array $storeProductIds): void;

    public function forgetPosition(string $categoryRef, ?int $storeProductId = null): void;
}
