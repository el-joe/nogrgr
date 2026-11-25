<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Owner portal › Domains (OWN-004): custom domains of the owner's stores. Removed
 * requests are listed only when the "Removed" status filter is chosen.
 *
 * @extends BaseDataTable<DomainRequest>
 */
final class OwnerDomainsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'tenant_id', 'search'];

    private int $ownerId = 0;

    public function __construct(private readonly DomainRequestRepositoryContract $requests) {}

    public function forOwner(int $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->filter($this->requests->listing(), []);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = DomainRequestStatus::tryFrom((string) ($filters['status'] ?? ''));

        return $this->requests->listing([...$filters, 'owner_id' => $this->ownerId])
            ->when($status === null, fn (Builder $query) => $query->where('domain_requests.status', '!=', DomainRequestStatus::Removed->value));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('host', fn (DomainRequest $request): string => view('platform::owner.domains._domain-cell', ['request' => $request])->render())
            ->editColumn('ssl_status', fn (DomainRequest $request): string => view('platform::admin.domains._ssl-cell', ['request' => $request])->render())
            ->editColumn('dns_status', fn (DomainRequest $request): string => view('platform::admin.domains._dns-cell', ['request' => $request])->render())
            ->editColumn('status', fn (DomainRequest $request): string => view('platform::owner.domains._status-cell', ['request' => $request])->render())
            ->addColumn('actions', fn (DomainRequest $request): string => view('platform::owner.domains._actions', ['request' => $request])->render())
            ->rawColumns(['host', 'ssl_status', 'dns_status', 'status', 'actions']);
    }
}
