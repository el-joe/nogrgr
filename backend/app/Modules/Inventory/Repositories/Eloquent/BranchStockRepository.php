<?php

namespace App\Modules\Inventory\Repositories\Eloquent;

use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Inventory\Repositories\Contracts\BranchStockRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<BranchStock>
 */
class BranchStockRepository extends BaseRepository implements BranchStockRepositoryContract
{
    protected function model(): string
    {
        return BranchStock::class;
    }

    public function levelsForProduct(int $productId): array
    {
        $levels = [];

        foreach ($this->query()->where('product_id', $productId)->get(['branch_id', 'variant_key', 'on_hand']) as $row) {
            $levels[$row->branch_id][$row->variant_key] = $row->on_hand;
        }

        return $levels;
    }

    public function put(int $branchId, int $productId, ?int $variantId, int $onHand): void
    {
        $this->query()->updateOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId, 'variant_key' => $variantId ?? 0],
            ['product_variant_id' => $variantId, 'on_hand' => $onHand],
        );
    }

    public function pruneVariants(int $productId, array $keepVariantKeys): void
    {
        $this->query()->where('product_id', $productId)->whereNotIn('variant_key', $keepVariantKeys)->delete();
    }

    public function totalsForProduct(int $productId): array
    {
        /** @var array<int, int> $totals */
        $totals = $this->query()
            ->where('branch_stock.product_id', $productId)
            ->whereHas('branch', static fn (Builder $query) => $query->where('is_active', true))
            ->selectRaw('variant_key, SUM(on_hand) as total')
            ->groupBy('variant_key')
            ->pluck('total', 'variant_key')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $totals;
    }

    public function branchTotals(int $branchId): array
    {
        $row = $this->query()->where('branch_id', $branchId)->where('on_hand', '>', 0)
            ->selectRaw('COUNT(*) as skus, COALESCE(SUM(on_hand), 0) as units')
            ->first();

        return ['skus' => (int) ($row->skus ?? 0), 'units' => (int) ($row->units ?? 0)];
    }

    public function forBranch(int $branchId): Builder
    {
        return $this->query()
            ->select('branch_stock.*')
            ->where('branch_stock.branch_id', $branchId)
            ->whereHas('product')
            ->with(['product', 'variant.options']);
    }

    public function adjust(int $branchId, int $productId, int $variantKey, int $delta): void
    {
        /** @var BranchStock|null $cell */
        $cell = $this->query()->where(['branch_id' => $branchId, 'product_id' => $productId, 'variant_key' => $variantKey])->lockForUpdate()->first();

        if ($cell === null) {
            if ($delta > 0) {
                $this->put($branchId, $productId, $variantKey === 0 ? null : $variantKey, $delta);
            }

            return;
        }

        $cell->forceFill(['on_hand' => max(0, $cell->on_hand + $delta)])->save();
    }

    public function branchesHolding(int $productId, int $variantKey): array
    {
        /** @var array<int, int> $rows */
        $rows = $this->query()
            ->where('product_id', $productId)
            ->where('variant_key', $variantKey)
            ->whereHas('branch', static fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('on_hand')
            ->orderBy('branch_id')
            ->pluck('on_hand', 'branch_id')
            ->map(static fn (mixed $units): int => (int) $units)
            ->all();

        return $rows;
    }

    public function tracksProduct(int $productId): bool
    {
        return $this->query()->where('product_id', $productId)->exists();
    }
}
