<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Support\UiKitSamples;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\CollectionDataTable;

/**
 * Server-side JSON for the component library's data table demo (Yajra, collection based).
 */
final class UiKitProductsDataTable
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  string  $actionsView  row actions partial (admin kit or vendor kit, VEN-093)
     */
    public function toJson(array $filters = [], string $actionsView = 'platform::admin.ui-kit._actions-cell'): JsonResponse
    {
        $filters = DataTableFilters::sanitize($filters);
        $rows = UiKitSamples::products();

        if (is_string($filters['status'] ?? null)) {
            $rows = $rows->where('status', $filters['status'])->values();
        }

        if (is_string($filters['search'] ?? null)) {
            $term = mb_strtolower($filters['search']);
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['name'].' '.$row['sku']), $term))->values();
        }

        return CollectionDataTable::create($rows->all())
            ->editColumn('name', fn (array $row): string => view('platform::admin.ui-kit._product-cell', ['row' => $row])->render())
            ->editColumn('status', fn (array $row): string => view('platform::admin.ui-kit._status-cell', ['row' => $row])->render())
            ->editColumn('price', fn (array $row): string => '<bdi dir="ltr">USD '.e($row['price']).'</bdi>')
            ->addColumn('actions', fn (array $row): string => view($actionsView, ['row' => $row])->render())
            ->rawColumns(['name', 'status', 'price', 'actions'])
            ->toJson();
    }
}
