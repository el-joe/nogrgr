<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Catalog\Services\Vendor\StoreProductPricingService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Products (VEN-010): imported central products as a card grid (one `card`
 * column). Filters: search (central name / sku), status, category. Order = store sort.
 *
 * @extends BaseDataTable<StoreProduct>
 */
final class StoreProductsDataTable extends BaseDataTable
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    public function __construct(
        private readonly StoreCatalogService $catalog,
        private readonly StoreProductPricingService $pricing,
        private readonly StoreCatalogReader $reader,
    ) {}

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
        return $this->reader->withRelations($this->catalog->listing($this->filters))
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->addColumn('card', fn (StoreProduct $product): string => view('catalog::vendor.products._card', [
                'product' => $product,
                'view' => $this->reader->view($product),
                'cheapest' => $this->pricing->cheapestRow($product),
            ])->render())
            ->rawColumns(['card'])
            ->only(['id', 'card']);
    }
}
