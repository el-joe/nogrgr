<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Products › New (VEN-011): offered central products not in the store yet,
 * newest first, selectable for import.
 *
 * @extends BaseDataTable<Product>
 */
final class NewProductsDataTable extends BaseDataTable
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    public function __construct(private readonly StoreCatalogService $catalog) {}

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
        return $this->catalog->newProducts($this->filters)->with('media')->orderByDesc('products.published_at')->orderByDesc('products.id');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->addColumn('card', static fn (Product $product): string => view('catalog::vendor.products._new-card', ['product' => $product])->render())
            ->rawColumns(['card'])
            ->only(['id', 'card']);
    }
}
