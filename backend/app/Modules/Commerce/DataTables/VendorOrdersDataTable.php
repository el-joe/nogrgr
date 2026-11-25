<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Orders (VEN-020, Figma 12:15191 "Order Queue"): product thumbnail + name,
 * customer, value, commission (vendor share), status, gateway, placed at, actions.
 * Filters: search, status, payment, gateway, kind, dates. Pending-payment checkouts
 * are hidden until the status filter asks for them.
 *
 * @extends BaseDataTable<Order>
 */
final class VendorOrdersDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'payment', 'gateway', 'kind', 'from', 'to'];

    public function __construct(private readonly OrderRepositoryContract $orders) {}

    protected function query(): Builder
    {
        return $this->orders->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->orders->listing($filters)
            ->when(($filters['status'] ?? null) === null, static fn (Builder $query) => $query->where('status', '!=', 'pending_payment'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('number', $direction($order)))
            ->orderColumn('customer', static fn (Builder $query, string $order) => $query->orderBy('customer_name', $direction($order)))
            ->orderColumn('value', static fn (Builder $query, string $order) => $query->orderBy('grand_total', $direction($order)))
            ->orderColumn('commission', static fn (Builder $query, string $order) => $query->orderBy('vendor_profit', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->orderColumn('gateway', static fn (Builder $query, string $order) => $query->orderBy('payment_gateway', $direction($order)))
            ->orderColumn('placed', static fn (Builder $query, string $order) => $query->orderBy('placed_at', $direction($order))->orderBy('id', $direction($order)))
            ->addColumn('order', fn (Order $order): string => view('commerce::vendor.orders._order-cell', ['order' => $order])->render())
            ->addColumn('customer', fn (Order $order): string => e((string) $order->customer_name))
            ->addColumn('value', fn (Order $order): string => '<span dir="ltr">'.e($order->money('grand_total')->format()).'</span>')
            ->addColumn('commission', fn (Order $order): string => '<span dir="ltr">'.e(Money::ofMinor($order->vendor_profit, $order->currency)->format()).'</span>')
            ->addColumn('status', fn (Order $order): string => view('commerce::partials.status-badge', ['value' => $order->status])->render())
            ->addColumn('gateway', fn (Order $order): string => e(self::gatewayLabel($order->payment_gateway)))
            ->addColumn('placed', fn (Order $order): string => e((string) $order->placed_at?->translatedFormat('D, d M Y')))
            ->addColumn('actions', fn (Order $order): string => view('commerce::vendor.orders._actions-cell', ['order' => $order])->render())
            ->rawColumns(['order', 'value', 'commission', 'status', 'actions']);
    }

    public static function gatewayLabel(PaymentGatewayCode|string|null $gateway): string
    {
        if ($gateway instanceof PaymentGatewayCode) {
            return $gateway->brand();
        }

        if ($gateway === null || $gateway === '') {
            return '—';
        }

        return PaymentGatewayCode::tryFrom($gateway)?->brand() ?? Str::headline($gateway);
    }
}
