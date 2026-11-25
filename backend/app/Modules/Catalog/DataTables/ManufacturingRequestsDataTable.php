<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Repositories\Contracts\ManufacturingRequestRepositoryContract;
use Yajra\DataTables\EloquentDataTable;

/**
 * Requests center › Manufacturing (ADM-018e): + quantity column.
 *
 * @extends ThreadedRequestsDataTable<ManufacturingRequest>
 */
final class ManufacturingRequestsDataTable extends ThreadedRequestsDataTable
{
    public function __construct(private readonly ManufacturingRequestRepositoryContract $requests) {}

    public function kind(): RequestKind
    {
        return RequestKind::Manufacturing;
    }

    protected function repository(): ManufacturingRequestRepositoryContract
    {
        return $this->requests;
    }

    protected function extraColumns(EloquentDataTable $table, string $prefix): array
    {
        $table->addColumn('quantity', fn (ManufacturingRequest $request): string => e(number_format($request->quantity)))
            ->orderColumn('quantity', $prefix.'.quantity $1');

        return ['quantity'];
    }
}
