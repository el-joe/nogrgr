<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Domains › Domain requests (ADM-045). Only the rendered columns leave the
 * server (the verification token and raw DNS answers stay on the detail page).
 *
 * @extends BaseDataTable<DomainRequest>
 */
final class DomainRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'tenant_id', 'search'];

    public function __construct(private readonly DomainRequestRepositoryContract $requests) {}

    protected function query(): Builder
    {
        return $this->requests->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->requests->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('domain', fn (DomainRequest $request): string => view('platform::admin.domains._domain-cell', ['request' => $request])->render())
            ->addColumn('store', fn (DomainRequest $request): string => view('platform::admin.domains._store-cell', ['request' => $request])->render())
            ->addColumn('dns', fn (DomainRequest $request): string => view('platform::admin.domains._dns-cell', ['request' => $request])->render())
            ->addColumn('ssl', fn (DomainRequest $request): string => view('platform::admin.domains._ssl-cell', ['request' => $request])->render())
            ->editColumn('status', fn (DomainRequest $request): string => view('platform::admin.compliance._badge-cell', ['tone' => $request->status->tone(), 'label' => $request->status->label()])->render())
            ->addColumn('actions', fn (DomainRequest $request): string => view('platform::admin.domains._actions', ['request' => $request])->render())
            ->orderColumn('domain', 'domain_requests.host $1')
            ->orderColumn('store', 'tenants.name $1')
            ->orderColumn('dns', 'domain_requests.dns_status $1')
            ->orderColumn('ssl', 'domain_requests.ssl_status $1')
            ->orderColumn('status', 'domain_requests.status $1')
            ->rawColumns(['domain', 'store', 'dns', 'ssl', 'status', 'actions'])
            ->only(['id', 'domain', 'store', 'dns', 'ssl', 'status', 'actions']);
    }
}
