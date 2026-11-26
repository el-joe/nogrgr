<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\StoreBadgePosition;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * The store's product order inside a central badge (tenant DB, decision #74).
 *
 * @extends RepositoryContract<StoreBadgePosition>
 */
interface StoreBadgeRepositoryContract extends RepositoryContract
{
    /**
     * store product id => position.
     *
     * @return array<int, int>
     */
    public function positions(int $badgeId): array;

    /**
     * @param  list<int>  $storeProductIds
     */
    public function savePositions(int $badgeId, array $storeProductIds): void;

    /**
     * Appends products after the current last position (known ones keep theirs).
     *
     * @param  list<int>  $storeProductIds
     */
    public function append(int $badgeId, array $storeProductIds): void;

    public function forget(int $badgeId, int $storeProductId): void;
}
