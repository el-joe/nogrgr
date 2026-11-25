<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Sales › Orders (ADM-020, Figma 48:16236): every store's orders from the central
 * order_facts — order + date, store, customer + city, total, payment + gateway, status.
 * Filters: status tab, payment, gateway, store, country, dates, search.
 *
 * @extends BaseDataTable<OrderFact>
 */
final class AdminOrdersDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'payment', 'gateway', 'tenant_id', 'country_id', 'from', 'to'];

    public function __construct(private readonly OrderFactRepositoryContract $facts) {}

    protected function query(): Builder
    {
        return $this->facts->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->facts->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('placed_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('vendor', static fn (Builder $query, string $order) => $query->orderBy('tenant_id', $direction($order)))
            ->orderColumn('customer', static fn (Builder $query, string $order) => $query->orderBy('customer_name', $direction($order)))
            ->orderColumn('total', static fn (Builder $query, string $order) => $query->orderBy('grand_total', $direction($order)))
            ->orderColumn('payment', static fn (Builder $query, string $order) => $query->orderBy('payment_status', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('order', fn (OrderFact $fact): string => view('commerce::admin.orders._order-cell', ['fact' => $fact, 'url' => route('admin.orders.show', $fact->id)])->render())
            ->addColumn('vendor', fn (OrderFact $fact): string => view('commerce::admin.orders._vendor-cell', ['fact' => $fact])->render())
            ->addColumn('customer', fn (OrderFact $fact): string => '<span class="block font-medium text-ink">'.e((string) $fact->customer_name).'</span><span class="text-xs text-ink-secondary">'.e((string) $fact->customer_city).'</span>')
            ->addColumn('total', fn (OrderFact $fact): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor($fact->grand_total, $fact->currency)->format()).'</span>')
            ->addColumn('payment', fn (OrderFact $fact): string => view('commerce::admin.orders._payment-cell', ['fact' => $fact])->render())
            ->addColumn('status', fn (OrderFact $fact): string => view('commerce::partials.status-badge', ['value' => OrderStatus::tryFrom($fact->status) ?? $fact->status])->render())
            ->addColumn('actions', fn (OrderFact $fact): string => view('commerce::admin.orders._actions-cell', ['fact' => $fact])->render())
            ->rawColumns(['order', 'vendor', 'customer', 'total', 'payment', 'status', 'actions']);
    }
}
