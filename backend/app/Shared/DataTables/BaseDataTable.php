<?php

namespace App\Shared\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;

/**
 * Server-side DataTable for list pages (index = Blade shell, data = this JSON).
 *
 * Subclasses provide the base query (from a repository) and customise columns:
 *
 *   final class ProductsDataTable extends BaseDataTable {
 *       public function __construct(private ProductRepositoryContract $products) {}
 *       protected function query(): Builder { return $this->products->query()->with('category'); }
 *       protected function configure(EloquentDataTable $table): EloquentDataTable {
 *           return $table->addColumn('actions', fn (Product $p) => view('catalog::admin.products._actions', ['product' => $p])->render())
 *                        ->rawColumns(['actions']);
 *       }
 *   }
 *
 * @template TModel of Model
 */
abstract class BaseDataTable
{
    /**
     * @return Builder<TModel>
     */
    abstract protected function query(): Builder;

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table;
    }

    /**
     * Apply request filters (beyond DataTables' own search/order) to the query.
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    protected function filter(Builder $query, array $filters): Builder
    {
        return $query;
    }

    /**
     * Build the JSON from a DataTables request, reading only the given filter keys
     * (structured DataTables parameters such as search[value] are handled safely).
     *
     * @param  list<string>  $filterKeys
     */
    public function toJsonFromRequest(Request $request, array $filterKeys = []): JsonResponse
    {
        return $this->toJson(DataTableFilters::fromRequest($request, $filterKeys));
    }

    /**
     * Filters are sanitized first (scalars / flat lists only), so a key that arrives
     * as an array (e.g. DataTables' own `search`) can never break a query.
     *
     * @param  array<string, mixed>  $filters
     */
    public function toJson(array $filters = []): JsonResponse
    {
        $table = new EloquentDataTable($this->filter($this->query(), DataTableFilters::sanitize($filters)));

        return $this->configure($table)->toJson();
    }
}
