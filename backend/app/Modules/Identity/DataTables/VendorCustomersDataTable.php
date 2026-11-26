<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Repositories\Contracts\VendorCustomerRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Customers (VEN-025, Figma 12:15544 "Customer List"): ID, customer (name +
 * e-mail), contact (phone), orders, status, lifetime value, last order, actions.
 * Filters: search, status, segment (buyers / no orders), joined from / to.
 *
 * @extends BaseDataTable<Customer>
 */
final class VendorCustomersDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'segment', 'from', 'to'];

    private bool $canManage = false;

    public function __construct(private readonly VendorCustomerRepositoryContract $customers) {}

    public function withManage(bool $canManage): self
    {
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->customers->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->customers->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('id', static fn (Builder $query, string $order) => $query->orderBy('id', $direction($order)))
            ->orderColumn('customer', static fn (Builder $query, string $order) => $query->orderBy('name', $direction($order)))
            ->orderColumn('contact', static fn (Builder $query, string $order) => $query->orderBy('phone', $direction($order)))
            ->orderColumn('orders', static fn (Builder $query, string $order) => $query->orderBy('orders_count', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->orderColumn('lifetime', static fn (Builder $query, string $order) => $query->orderBy('lifetime_minor', $direction($order)))
            ->orderColumn('last_order', static fn (Builder $query, string $order) => $query->orderBy('last_order_at', $direction($order))->orderBy('id', $direction($order)))
            ->editColumn('id', static fn (Customer $customer): string => '<span class="text-ink-secondary" dir="ltr">#'.$customer->id.'</span>')
            ->addColumn('customer', static fn (Customer $customer): string => view('identity::vendor.customers._customer-cell', ['customer' => $customer])->render())
            ->addColumn('contact', static fn (Customer $customer): string => $customer->phone !== null ? '<span dir="ltr">'.e($customer->phone).'</span>' : '<span class="text-ink-muted">—</span>')
            ->addColumn('orders', static fn (Customer $customer): string => number_format((int) $customer->getAttribute('orders_count')))
            ->addColumn('status', static fn (Customer $customer): string => view('identity::vendor.customers._status', ['customer' => $customer])->render())
            ->addColumn('lifetime', static fn (Customer $customer): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor((int) $customer->getAttribute('lifetime_minor'))->format()).'</span>')
            ->addColumn('last_order', static function (Customer $customer): string {
                $last = $customer->getAttribute('last_order_at');

                return $last !== null ? e(Carbon::parse((string) $last)->translatedFormat('D, d M Y')) : '<span class="text-ink-muted">—</span>';
            })
            ->addColumn('actions', fn (Customer $customer): string => view('identity::vendor.customers._actions-cell', ['customer' => $customer, 'canManage' => $this->canManage])->render())
            ->rawColumns(['id', 'customer', 'contact', 'status', 'lifetime', 'last_order', 'actions']);
    }
}
