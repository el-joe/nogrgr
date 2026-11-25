<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\OwnProductService;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Own products card grids: section "See all" pages (VEN-016a) and the inventory page
 * (VEN-016b, `view=inventory` renders the stock editor card).
 *
 * @extends BaseDataTable<StoreProduct>
 */
final class OwnProductsDataTable extends BaseDataTable
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    private bool $inventory = false;

    public function __construct(
        private readonly OwnProductService $own,
        private readonly StoreProductPricingService $pricing,
    ) {}

    public function forInventory(bool $inventory): self
    {
        $this->inventory = $inventory;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function toJson(array $filters = []): JsonResponse
    {
        $this->filters = $filters;

        return parent::toJson($filters);
    }

    protected function query(): Builder
    {
        $query = $this->own->listing($this->filters)->with(['media', 'variants']);

        return isset($this->filters['section']) ? $query : $query->orderBy('sort_order')->orderByDesc('id');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->addColumn('card', fn (StoreProduct $product): string => view($this->inventory ? 'catalog::vendor.own._inventory-card' : 'catalog::vendor.own._card', [
                'product' => $product,
                'price' => $this->pricing->ownPrice($product),
            ])->render())
            ->rawColumns(['card'])
            ->only(['id', 'card']);
    }
}
