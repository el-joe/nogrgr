<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Category>
 */
interface CategoryRepositoryContract extends RepositoryContract
{
    /**
     * Every category (flat) in tree order, with `products_count`.
     *
     * @return Collection<int, Category>
     */
    public function allForTree(): Collection;

    /**
     * id => parent_id of every category.
     *
     * @return array<int, int|null>
     */
    public function parentMap(): array;

    /**
     * Writes parent and position of many categories: id => [parent_id, sort_order].
     *
     * @param  array<int, array{parent_id: int|null, sort_order: int}>  $positions
     */
    public function savePositions(array $positions): void;

    public function nextSortOrder(?int $parentId): int;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;

    public function hasChildren(Category $category): bool;

    /**
     * Products of a category in their category order (with media).
     *
     * @return Collection<int, Product>
     */
    public function productsInOrder(Category $category): Collection;

    /**
     * @return list<int>
     */
    public function productIds(Category $category): array;

    /**
     * Sets category_product.sort_order to the index of each id.
     *
     * @param  list<int>  $productIds
     */
    public function saveProductOrder(Category $category, array $productIds): void;
}
