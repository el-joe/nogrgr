<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Inventory\Repositories\Contracts\BranchStockRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Stock per branch (ADM-011h, ADM-030a): the product form's branch × variant grid and
 * the branch CSV import. Product / variant `stock` columns are kept as the sum over the
 * active branches and StockChanged is fired after every change.
 */
class StockService extends BaseService
{
    public function __construct(
        private readonly BranchStockRepositoryContract $stock,
        private readonly ProductRepositoryContract $products,
    ) {}

    /**
     * [branch_id][variant_key] => on_hand (variant_key 0 = product without variants).
     *
     * @return array<int, array<int, int>>
     */
    public function levels(Product $product): array
    {
        return $this->stock->levelsForProduct($product->id);
    }

    /**
     * Writes the grid of a product and recomputes its totals. Variant keys must be ids of
     * the product's current variants (or 0 when it has none); rows of removed variants go.
     *
     * @param  array<int, array<int, int>>  $levels  [branch_id][variant_key] => on_hand
     * @param  list<int>  $variantIds  current variant ids (empty = simple product)
     */
    public function saveLevels(Product $product, array $levels, array $variantIds): void
    {
        $keys = $variantIds === [] ? [0] : $variantIds;

        $this->transaction(function () use ($product, $levels, $keys): void {
            foreach ($levels as $branchId => $cells) {
                foreach ($cells as $variantKey => $onHand) {
                    if (in_array((int) $variantKey, $keys, true)) {
                        $this->stock->put((int) $branchId, $product->id, $variantKey === 0 ? null : (int) $variantKey, max(0, (int) $onHand));
                    }
                }
            }

            $this->stock->pruneVariants($product->id, $keys);
        }, $this->connection());

        $this->recompute($product, $keys);
    }

    /**
     * CSV rows (sku, on_hand) for one branch.
     *
     * @param  list<array{sku: string, on_hand: int}>  $rows
     * @return array{updated: int, unknown: list<string>}
     */
    public function importForBranch(Branch $branch, array $rows): array
    {
        $updated = 0;
        $unknown = [];
        $touched = [];

        $this->transaction(function () use ($branch, $rows, &$updated, &$unknown, &$touched): void {
            foreach ($rows as $row) {
                $target = $this->products->findBySku($row['sku']);

                if ($target === null) {
                    $unknown[] = $row['sku'];

                    continue;
                }

                $this->stock->put($branch->id, $target['product_id'], $target['variant_id'], max(0, $row['on_hand']));
                $touched[$target['product_id']] = true;
                $updated++;
            }
        }, $this->connection());

        foreach (array_keys($touched) as $productId) {
            /** @var Product|null $product */
            $product = $this->products->find($productId);

            if ($product !== null) {
                $this->recompute($product, null, $branch->id);
            }
        }

        return ['updated' => $updated, 'unknown' => array_values(array_unique($unknown))];
    }

    /**
     * @param  list<int>|null  $keys
     */
    private function recompute(Product $product, ?array $keys = null, ?int $branchId = null): void
    {
        $totals = $this->stock->totalsForProduct($product->id);
        $variantTotals = [];

        foreach ($product->variants()->pluck('id') as $variantId) {
            $variantTotals[(int) $variantId] = $totals[(int) $variantId] ?? 0;
        }

        $total = $variantTotals === [] ? ($totals[0] ?? 0) : array_sum($variantTotals);
        $this->products->saveStockTotals($product, $total, $variantTotals);

        StockChanged::dispatch($product->id, $total, $branchId);
    }

    private function connection(): ?string
    {
        return (new BranchStock)->getConnectionName();
    }
}
