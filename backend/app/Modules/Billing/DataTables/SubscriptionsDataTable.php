<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin subscriptions list (ADM-047).
 *
 * @extends BaseDataTable<Subscription>
 */
final class SubscriptionsDataTable extends BaseDataTable
{
    public function __construct(private readonly SubscriptionRepositoryContract $subscriptions) {}

    protected function query(): Builder
    {
        return $this->subscriptions->listing();
    }

    /**
     * The repository listing applies every filter (status, search…).
     */
    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->subscriptions->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('store', fn (Subscription $subscription): string => view('billing::admin._store-cell', ['tenant' => $subscription->tenant])->render())
            ->editColumn('plan_id', fn (Subscription $subscription): string => view('billing::admin.subscriptions._plan-cell', ['subscription' => $subscription])->render())
            ->addColumn('kind', fn (Subscription $subscription): string => e($subscription->kind()->label()))
            ->editColumn('amount_minor', fn (Subscription $subscription): string => '<bdi dir="ltr">'.e($subscription->amount()->format()).'</bdi>')
            ->editColumn('current_period_ends_at', fn (Subscription $subscription): string => e($subscription->nextBillingAt()?->translatedFormat('j M Y') ?? '—'))
            ->editColumn('status', fn (Subscription $subscription): string => view('billing::admin._status', ['status' => $subscription->status])->render())
            ->addColumn('actions', fn (Subscription $subscription): string => view('billing::admin.subscriptions._actions', ['subscription' => $subscription])->render())
            ->rawColumns(['store', 'plan_id', 'amount_minor', 'status', 'actions']);
    }
}
