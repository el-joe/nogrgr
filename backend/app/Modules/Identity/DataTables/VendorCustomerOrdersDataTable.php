<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Commerce\DataTables\VendorOrdersDataTable;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Customers › detail (VEN-025b, Figma 84:105854 "Orders & payments"): the
 * customer's placed orders — number + date, total, payment (gateway + payment status),
 * order status. Links open the order (VEN-021) when the staff member may view orders.
 *
 * @extends BaseDataTable<Order>
 */
final class VendorCustomerOrdersDataTable extends BaseDataTable
{
    private int $customerId = 0;

    private bool $linkOrders = false;

    public function __construct(private readonly OrderRepositoryContract $orders) {}

    public function forCustomer(int $customerId, bool $linkOrders): self
    {
        $this->customerId = $customerId;
        $this->linkOrders = $linkOrders;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->orders->query()
            ->where('customer_id', $this->customerId)
            ->where('status', '!=', OrderStatus::PendingPayment->value);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('placed_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('total', static fn (Builder $query, string $order) => $query->orderBy('grand_total', $direction($order)))
            ->orderColumn('payment', static fn (Builder $query, string $order) => $query->orderBy('payment_gateway', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('order', fn (Order $order): string => view('identity::vendor.customers._order-cell', ['order' => $order, 'link' => $this->linkOrders])->render())
            ->addColumn('total', static fn (Order $order): string => '<span class="font-semibold text-ink" dir="ltr">'.e($order->money('grand_total')->format()).'</span>')
            ->addColumn('payment', static fn (Order $order): string => view('commerce::partials.status-badge', ['value' => $order->payment_status, 'label' => VendorOrdersDataTable::gatewayLabel($order->payment_gateway)])->render())
            ->addColumn('status', static fn (Order $order): string => view('commerce::partials.status-badge', ['value' => $order->status])->render())
            ->rawColumns(['order', 'total', 'payment', 'status']);
    }
}
