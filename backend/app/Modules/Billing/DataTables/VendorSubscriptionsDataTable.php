<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Subscription & balance › Subscriptions history (VEN-060c): every subscription
 * of the current store (tenant fixed by the controller), with its latest invoice.
 *
 * @extends BaseDataTable<Subscription>
 */
final class VendorSubscriptionsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'search'];

    private int $tenantId = 0;

    public function __construct(private readonly SubscriptionRepositoryContract $subscriptions) {}

    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->subscriptions->listing(['tenant_id' => $this->tenantId]);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->subscriptions->listing([...$filters, 'tenant_id' => $this->tenantId]);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('plan', fn (Subscription $subscription): string => '<span class="font-medium text-ink">'.e((string) $subscription->plan?->name).'</span>')
            ->editColumn('term', fn (Subscription $subscription): string => e($subscription->status === SubscriptionStatus::Trial ? __('billing::owner.subscriptions.trial') : $subscription->term->label()))
            ->addColumn('period', fn (Subscription $subscription): string => view('billing::vendor.subscription._period-cell', ['subscription' => $subscription])->render())
            ->editColumn('amount_minor', fn (Subscription $subscription): string => '<bdi dir="ltr" class="font-medium">'.e($subscription->amount()->format()).'</bdi>')
            ->editColumn('status', fn (Subscription $subscription): string => view('billing::admin._status', ['status' => $subscription->status])->render())
            ->addColumn('invoice', fn (Subscription $subscription): string => view('billing::vendor.subscription._invoice-cell', ['invoice' => $subscription->latestInvoice])->render())
            ->rawColumns(['plan', 'period', 'amount_minor', 'status', 'invoice']);
    }
}
