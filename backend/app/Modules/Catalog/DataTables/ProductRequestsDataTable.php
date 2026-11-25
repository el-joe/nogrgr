<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Repositories\Contracts\ProductRequestRepositoryContract;
use Yajra\DataTables\EloquentDataTable;

/**
 * Requests center › Product requests (ADM-018c): + priority column.
 *
 * @extends ThreadedRequestsDataTable<ProductRequest>
 */
final class ProductRequestsDataTable extends ThreadedRequestsDataTable
{
    public function __construct(private readonly ProductRequestRepositoryContract $requests) {}

    public function kind(): RequestKind
    {
        return RequestKind::Product;
    }

    protected function repository(): ProductRequestRepositoryContract
    {
        return $this->requests;
    }

    protected function extraColumns(EloquentDataTable $table, string $prefix): array
    {
        $table->addColumn('priority', fn (ProductRequest $request): string => view('catalog::admin.requests.partials._badge', ['tone' => $request->priority->tone(), 'label' => $request->priority->label()])->render())
            ->orderColumn('priority', "FIELD({$prefix}.priority, 'low', 'normal', 'high', 'urgent') \$1");

        return ['priority'];
    }
}
