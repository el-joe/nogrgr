<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Services\Requests\EditRequestService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Requests center › Product edit requests (ADM-018a).
 *
 * @extends BaseDataTable<EditRequest>
 */
final class EditRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'tenant_id', 'search'];

    public function __construct(private readonly EditRequestService $editRequests) {}

    protected function query(): Builder
    {
        return $this->editRequests->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->editRequests->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('request', fn (EditRequest $request): string => view('catalog::admin.requests.edits._request-cell', ['request' => $request])->render())
            ->addColumn('vendor', fn (EditRequest $request): string => view('catalog::admin.requests.partials._vendor-cell', ['name' => $request->getAttribute('store_name'), 'slug' => $request->getAttribute('store_slug')])->render())
            ->addColumn('fields', fn (EditRequest $request): string => view('catalog::admin.requests.edits._fields-cell', ['request' => $request])->render())
            ->editColumn('status', fn (EditRequest $request): string => view('catalog::admin.requests.partials._badge', ['tone' => $request->status->tone(), 'label' => $request->status->label()])->render())
            ->addColumn('submitted', fn (EditRequest $request): string => view('catalog::admin.requests.partials._date-cell', ['date' => $request->created_at])->render())
            ->addColumn('actions', fn (EditRequest $request): string => view('catalog::admin.requests.edits._actions', ['request' => $request])->render())
            ->orderColumn('request', 'catalog_edit_requests.id $1')
            ->orderColumn('vendor', 'tenants.name $1')
            ->orderColumn('status', 'catalog_edit_requests.status $1')
            ->orderColumn('submitted', 'catalog_edit_requests.created_at $1')
            ->rawColumns(['request', 'vendor', 'fields', 'status', 'submitted', 'actions'])
            ->only(['id', 'request', 'vendor', 'fields', 'status', 'submitted', 'actions']);
    }
}
