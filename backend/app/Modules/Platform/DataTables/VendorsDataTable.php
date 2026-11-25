<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\VendorProfileService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin vendors list (ADM-040). Only the rendered columns leave the server (the
 * tenant `data` JSON may hold the provisioning payload).
 *
 * @extends BaseDataTable<Tenant>
 */
final class VendorsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'subscription_status', 'plan_id', 'country_id', 'search'];

    /** @var array<int, string>|null */
    private ?array $planNames = null;

    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly PlanRepositoryContract $plans,
    ) {}

    protected function query(): Builder
    {
        return $this->tenants->listing();
    }

    /**
     * The repository listing applies every filter.
     */
    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->tenants->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('store', fn (Tenant $tenant): string => view('platform::admin.vendors._store-cell', ['tenant' => $tenant])->render())
            ->addColumn('owner', fn (Tenant $tenant): string => view('platform::admin.vendors._owner-cell', ['tenant' => $tenant])->render())
            ->addColumn('plan', fn (Tenant $tenant): string => view('platform::admin.vendors._plan-cell', ['name' => $this->planName($tenant)])->render())
            ->editColumn('profit_percent_bps', fn (Tenant $tenant): string => '<bdi dir="ltr">'.e(VendorProfileService::percent($tenant->profit_percent_bps)).'</bdi>')
            ->editColumn('status', fn (Tenant $tenant): string => view('platform::admin.vendors._status-cell', ['tenant' => $tenant])->render())
            ->addColumn('actions', fn (Tenant $tenant): string => view('platform::admin.vendors._actions', ['tenant' => $tenant])->render())
            ->orderColumn('store', 'tenants.name $1')
            ->orderColumn('owner', 'tenants.owner_name $1')
            ->orderColumn('profit_percent_bps', 'tenants.profit_percent_bps $1')
            ->orderColumn('status', 'tenants.status $1')
            ->orderColumn('created_at', 'tenants.id $1')
            ->rawColumns(['store', 'owner', 'plan', 'profit_percent_bps', 'status', 'actions'])
            ->only(['id', 'store', 'owner', 'plan', 'profit_percent_bps', 'status', 'actions']);
    }

    private function planName(Tenant $tenant): ?string
    {
        $planId = $tenant->getAttribute('plan_id');

        if ($planId === null) {
            return null;
        }

        $this->planNames ??= $this->plans->options();

        return $this->planNames[(int) $planId] ?? null;
    }
}
