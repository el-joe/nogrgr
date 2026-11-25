<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Today's chances (VEN-015): offered central products ranked by sales, then newest;
 * cards show the "already added" state for products in the store.
 *
 * @extends BaseDataTable<Product>
 */
final class OpportunitiesDataTable extends BaseDataTable
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    /**
     * @var list<int>|null
     */
    private ?array $imported = null;

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
        return $this->catalog->opportunities($this->filters)->with('media')->orderByDesc('products.sold_count')->orderByDesc('products.published_at')->orderByDesc('products.id');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $this->imported ??= $this->catalog->importedCentralIds();

        return $table
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->addColumn('card', fn (Product $product): string => view('catalog::vendor.opportunities._card', [
                'product' => $product,
                'added' => in_array($product->id, $this->imported ?? [], true),
            ])->render())
            ->rawColumns(['card'])
            ->only(['id', 'card']);
    }
}
