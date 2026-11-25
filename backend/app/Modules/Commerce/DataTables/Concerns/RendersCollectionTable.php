<?php

namespace App\Modules\Commerce\DataTables\Concerns;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;

/**
 * Server-side DataTables JSON over an in-memory list (the merged promotion readers of a
 * store are small and cross two databases, so they cannot be one SQL query). Items are
 * filtered by the caller, sorted here on raw sort keys (never on rendered HTML), paged
 * by Yajra and rendered row by row; every cell is escaped by its renderer.
 */
trait RendersCollectionTable
{
    /**
     * @template TItem
     *
     * @param  Collection<int, TItem>  $items
     * @param  array<string, Closure(TItem): (int|float|string)>  $sorters  column data name => sort key
     * @param  Closure(TItem): array<string, string>  $render
     * @param  list<string>  $columns
     */
    protected function respond(Request $request, Collection $items, array $sorters, Closure $render, array $columns): JsonResponse
    {
        $index = filter_var($request->input('order.0.column'), FILTER_VALIDATE_INT);
        $column = $index === false || $index === null ? null : $request->input("columns.{$index}.data");
        $descending = $request->input('order.0.dir') === 'desc';

        if (is_string($column) && isset($sorters[$column])) {
            $items = $items->sortBy($sorters[$column], SORT_NATURAL | SORT_FLAG_CASE, $descending);
        }

        $rows = $items->values()->map($render);

        return CollectionDataTable::create($rows->all())
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->rawColumns($columns)
            ->toJson();
    }
}
