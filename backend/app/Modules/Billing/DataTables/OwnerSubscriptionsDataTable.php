<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Owner portal › Subscriptions (OWN-003): every subscription of the owner's stores.
 * The owner filter is fixed by the controller; status / store / search come from the
 * request.
 *
 * @extends BaseDataTable<Subscription>
 */
final class OwnerSubscriptionsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'tenant_id', 'search'];

    private int $ownerId = 0;

    public function __construct(private readonly SubscriptionRepositoryContract $subscriptions) {}

    public function forOwner(int $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->subscriptions->listing(['owner_id' => $this->ownerId]);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->subscriptions->listing([...$filters, 'owner_id' => $this->ownerId]);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('store', fn (Subscription $subscription): string => view('billing::owner._store-cell', ['tenant' => $subscription->tenant])->render())
            ->addColumn('plan', fn (Subscription $subscription): string => e((string) $subscription->plan?->name))
            ->editColumn('term', fn (Subscription $subscription): string => e($subscription->status === SubscriptionStatus::Trial ? __('billing::owner.subscriptions.trial') : $subscription->term->label()))
            ->addColumn('renews_on', fn (Subscription $subscription): string => view('billing::owner.subscriptions._renews-cell', ['subscription' => $subscription])->render())
            ->editColumn('amount_minor', fn (Subscription $subscription): string => '<bdi dir="ltr" class="font-medium">'.e($subscription->amount()->format()).'</bdi>')
            ->editColumn('status', fn (Subscription $subscription): string => view('billing::admin._status', ['status' => $subscription->status])->render())
            ->addColumn('actions', fn (Subscription $subscription): string => view('billing::owner.subscriptions._actions', ['subscription' => $subscription])->render())
            ->rawColumns(['store', 'renews_on', 'amount_minor', 'status', 'actions']);
    }
}
