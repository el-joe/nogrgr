<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin plans list (ADM-046).
 *
 * @extends BaseDataTable<Plan>
 */
final class PlansDataTable extends BaseDataTable
{
    public function __construct(private readonly PlanRepositoryContract $plans) {}

    protected function query(): Builder
    {
        return $this->plans->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;

        return $query
            ->when(is_string($status) && PlanStatus::tryFrom($status) !== null, fn (Builder $q) => $q->where('status', $status))
            ->when(is_string($search), fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('name', fn (Plan $plan): string => view('billing::admin.plans._name-cell', ['plan' => $plan])->render())
            ->editColumn('monthly_price_minor', fn (Plan $plan): string => '<bdi dir="ltr">'.e($plan->priceFor(BillingTerm::Monthly)->format()).'</bdi>')
            ->editColumn('yearly_price_minor', fn (Plan $plan): string => '<bdi dir="ltr">'.e($plan->priceFor(BillingTerm::Yearly)->format()).'</bdi>')
            ->editColumn('trial_days', fn (Plan $plan): string => e(trans_choice('billing::plans.trial_days_value', $plan->trial_days, ['count' => $plan->trial_days])))
            ->editColumn('open_subscriptions_count', fn (Plan $plan): string => e((string) ($plan->open_subscriptions_count ?? 0)))
            ->editColumn('status', fn (Plan $plan): string => view('billing::admin._status', ['status' => $plan->status])->render())
            ->addColumn('actions', fn (Plan $plan): string => view('billing::admin.plans._actions', ['plan' => $plan])->render())
            ->rawColumns(['name', 'monthly_price_minor', 'yearly_price_minor', 'status', 'actions']);
    }
}
