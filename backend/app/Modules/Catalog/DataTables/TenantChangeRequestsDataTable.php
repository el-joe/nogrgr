<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use App\Modules\Catalog\Services\Requests\TenantChangeService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Requests center › Tenant changes (ADM-018i): selling countries / categories.
 *
 * @extends BaseDataTable<TenantChangeRequest>
 */
final class TenantChangeRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['type', 'status', 'tenant_id', 'search'];

    public function __construct(private readonly TenantChangeService $changes) {}

    protected function query(): Builder
    {
        return $this->changes->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->changes->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('vendor', fn (TenantChangeRequest $request): string => view('catalog::admin.requests.partials._vendor-cell', ['name' => $request->getAttribute('store_name'), 'slug' => $request->getAttribute('store_slug')])->render())
            ->editColumn('type', fn (TenantChangeRequest $request): string => view('catalog::admin.requests.partials._badge', ['tone' => $request->type->tone(), 'label' => $request->type->label()])->render())
            ->addColumn('change', fn (TenantChangeRequest $request): string => view('catalog::admin.requests.tenant-changes._change-cell', [
                'added' => $this->changes->names($request->type, $request->addedIds()),
                'removed' => $this->changes->names($request->type, $request->removedIds()),
            ])->render())
            ->editColumn('status', fn (TenantChangeRequest $request): string => view('catalog::admin.requests.partials._badge', ['tone' => $request->status->tone(), 'label' => $request->status->label()])->render())
            ->addColumn('submitted', fn (TenantChangeRequest $request): string => view('catalog::admin.requests.partials._date-cell', ['date' => $request->created_at])->render())
            ->addColumn('actions', fn (TenantChangeRequest $request): string => view('catalog::admin.requests.tenant-changes._actions', ['request' => $request])->render())
            ->orderColumn('vendor', 'tenants.name $1')
            ->orderColumn('type', 'catalog_tenant_change_requests.type $1')
            ->orderColumn('status', 'catalog_tenant_change_requests.status $1')
            ->orderColumn('submitted', 'catalog_tenant_change_requests.created_at $1')
            ->rawColumns(['vendor', 'type', 'change', 'status', 'submitted', 'actions'])
            ->only(['id', 'vendor', 'type', 'change', 'status', 'submitted', 'actions']);
    }
}
