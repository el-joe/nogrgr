<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Catalog › Products list (ADM-010, server-side). Filters: tab, search, status,
 * category, scope, stock. Trash tab lists soft-deleted products with Restore.
 *
 * @extends BaseDataTable<Product>
 */
final class ProductsDataTable extends BaseDataTable
{
    public const FILTERS = ['tab', 'search', 'status', 'category_id', 'scope', 'stock'];

    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    public function __construct(private readonly ProductRepositoryContract $products) {}

    protected function query(): Builder
    {
        return $this->products->listing($this->filters)
            ->with(['media', 'categories'])
            ->withCount('variants');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $query;
    }

    public function toJson(array $filters = []): JsonResponse
    {
        $this->filters = DataTableFilters::sanitize($filters);

        return parent::toJson($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('products.name->'.$locale, $direction($order)))
            ->orderColumn('price', static fn (Builder $query, string $order) => $query->orderByRaw('COALESCE(NULLIF(sale_price, 0), base_price) '.$direction($order)))
            ->orderColumn('stock', static fn (Builder $query, string $order) => $query->orderBy('stock', $direction($order)))
            ->orderColumn('updated_at', static fn (Builder $query, string $order) => $query->orderBy('products.updated_at', $direction($order)))
            ->editColumn('name', fn (Product $product): string => view('catalog::admin.products._product-cell', ['product' => $product])->render())
            ->addColumn('category', fn (Product $product): string => e((string) ($product->categories->first()->name ?? '—')))
            ->addColumn('price', fn (Product $product): string => view('catalog::admin.products._price-cell', ['product' => $product])->render())
            ->editColumn('stock', fn (Product $product): string => '<span dir="ltr" class="font-medium">'.e(number_format($product->stock)).'</span>')
            ->editColumn('status', fn (Product $product): string => view('catalog::admin.products._status-cell', ['product' => $product])->render())
            ->editColumn('updated_at', fn (Product $product): string => e((string) $product->updated_at?->diffForHumans()))
            ->addColumn('actions', fn (Product $product): string => view('catalog::admin.products._actions-cell', ['product' => $product])->render())
            ->rawColumns(['name', 'price', 'stock', 'status', 'actions']);
    }
}
