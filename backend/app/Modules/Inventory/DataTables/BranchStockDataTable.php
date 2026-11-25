<?php

namespace App\Modules\Inventory\DataTables;

use App\Modules\Inventory\Models\BranchStock;
use App\Modules\Inventory\Repositories\Contracts\BranchStockRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Stock of one branch (ADM-030a "Stock per variant"): variant, on hand, reserved, available.
 *
 * @extends BaseDataTable<BranchStock>
 */
final class BranchStockDataTable extends BaseDataTable
{
    public const FILTERS = ['search'];

    private int $branchId = 0;

    public function __construct(private readonly BranchStockRepositoryContract $stock) {}

    public function forBranch(int $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->stock->forBranch($this->branchId);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query->when($search !== null, function (Builder $query) use ($search): void {
            $term = '%'.mb_strtolower(addcslashes((string) $search, '%_\\')).'%';
            $query->where(fn (Builder $query) => $query
                ->whereHas('product', static fn (Builder $product) => $product
                    ->whereRaw('LOWER(sku) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`name`, "$.en"))) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`name`, "$.ar"))) LIKE ?', [$term]))
                ->orWhereHas('variant', static fn (Builder $variant) => $variant->whereRaw('LOWER(sku) LIKE ?', [$term])));
        });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->addColumn('variant', fn (BranchStock $row): string => view('inventory::admin.branches._stock-variant-cell', ['row' => $row])->render())
            ->editColumn('on_hand', fn (BranchStock $row): string => '<span dir="ltr">'.e(number_format($row->on_hand)).'</span>')
            ->editColumn('reserved', fn (BranchStock $row): string => '<span dir="ltr">'.e(number_format($row->reserved)).'</span>')
            ->addColumn('available', fn (BranchStock $row): string => '<span dir="ltr" class="font-medium">'.e(number_format($row->available())).'</span>')
            ->orderColumn('available', static fn (Builder $query, string $order) => $query->orderByRaw('(on_hand - reserved) '.($order === 'desc' ? 'desc' : 'asc')))
            ->orderColumn('variant', static fn (Builder $query, string $order) => $query->orderBy('product_id', $order === 'desc' ? 'desc' : 'asc'))
            ->rawColumns(['variant', 'on_hand', 'reserved', 'available']);
    }
}
