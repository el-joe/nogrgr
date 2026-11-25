<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductBadgeAssignment;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Badge lists use country_id 0 (every country) from the admin hub.
 *
 * @extends RepositoryContract<ProductBadge>
 */
interface ProductBadgeRepositoryContract extends RepositoryContract
{
    public function findByType(BadgeType $type): ?ProductBadge;

    /**
     * @return Collection<int, ProductBadge>
     */
    public function allOrdered(): Collection;

    /**
     * Assignments of the badge in order, with product (media + categories).
     *
     * @return Collection<int, ProductBadgeAssignment>
     */
    public function assignments(ProductBadge $badge): Collection;

    /**
     * @return list<int>
     */
    public function assignedProductIds(ProductBadge $badge): array;

    /**
     * badge id => assigned products count.
     *
     * @return array<int, int>
     */
    public function counts(): array;

    /**
     * Appends products (already assigned ones are skipped) after the current last position.
     *
     * @param  list<int>  $productIds
     * @return int number of products added
     */
    public function append(ProductBadge $badge, array $productIds): int;

    public function remove(ProductBadge $badge, int $productId): bool;

    /**
     * @param  list<int>  $productIds
     */
    public function saveOrder(ProductBadge $badge, array $productIds): void;

    /**
     * Replaces the whole list with the given products, in order.
     *
     * @param  list<int>  $productIds
     */
    public function replace(ProductBadge $badge, array $productIds): void;
}
