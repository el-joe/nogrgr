<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Inventory\Models\BranchStock;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<BranchStock>
 */
interface BranchStockRepositoryContract extends RepositoryContract
{
    /**
     * Levels of a product: [branch_id][variant_key] => on_hand.
     *
     * @return array<int, array<int, int>>
     */
    public function levelsForProduct(int $productId): array;

    /**
     * Upserts on_hand of one cell (reserved is kept).
     */
    public function put(int $branchId, int $productId, ?int $variantId, int $onHand): void;

    /**
     * Removes rows of variants that no longer exist.
     *
     * @param  list<int>  $keepVariantKeys
     */
    public function pruneVariants(int $productId, array $keepVariantKeys): void;

    /**
     * Totals per variant_key over every active branch: variant_key => on_hand sum.
     *
     * @return array<int, int>
     */
    public function totalsForProduct(int $productId): array;

    /**
     * @return array{skus: int, units: int}
     */
    public function branchTotals(int $branchId): array;

    /**
     * Stock rows of a branch with product + variant, for its stock table.
     *
     * @return Builder<BranchStock>
     */
    public function forBranch(int $branchId): Builder;

    /**
     * Adds $delta units (may be negative) to one cell; on_hand never drops below 0. A
     * missing cell is created for a positive delta.
     */
    public function adjust(int $branchId, int $productId, int $variantKey, int $delta): void;

    /**
     * Active branches holding the cell, the most stocked first: branch_id => on_hand.
     *
     * @return array<int, int>
     */
    public function branchesHolding(int $productId, int $variantKey): array;

    /**
     * Whether any branch tracks stock of the product.
     */
    public function tracksProduct(int $productId): bool;
}
