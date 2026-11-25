<?php

namespace App\Modules\Billing\DataTables\Finance;

use App\Modules\Billing\Enums\SettlementMethod;
use App\Modules\Commerce\Models\OrderSettlement;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\DataTables\BaseDataTable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Settlements vendor → platform, one row per payment (ADM-051, Figma 74:60809 / VEN-063
 * 88:111108): reference + date, store, orders covered, amount, gateway / method, status.
 *
 * @extends BaseDataTable<OrderSettlement>
 */
final class SettlementsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'gateway', 'tenant_id'];

    private ?int $tenantId = null;

    private string $surface = 'admin';

    public function __construct(private readonly OrderSettlementRepositoryContract $settlements) {}

    public function forTenant(?int $tenantId, string $surface): self
    {
        $this->tenantId = $tenantId;
        $this->surface = $surface;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->settlements->grouped([], $this->tenantId);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->settlements->grouped($filters, $this->tenantId)->with('tenant');
    }

    public static function gatewayLabel(?string $gateway, ?string $method): string
    {
        if ($method !== null && $method !== '') {
            return SettlementMethod::tryFrom($method)?->label() ?? $method;
        }

        return PaymentGatewayCode::tryFrom((string) $gateway)?->brand() ?? (string) $gateway;
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('settlement', static fn (Builder $query, string $order) => $query->orderBy('created_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('orders', static fn (Builder $query, string $order) => $query->orderBy('orders_count', $direction($order)))
            ->orderColumn('amount', static fn (Builder $query, string $order) => $query->orderBy('amount', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('settlement', fn (OrderSettlement $row): string => '<span class="block font-medium text-ink" dir="ltr">'.e((string) $row->reference).'</span><span class="text-xs text-ink-secondary">'.e((string) $row->created_at?->locale(app()->getLocale())->translatedFormat('j M Y')).'</span>')
            ->addColumn('store', fn (OrderSettlement $row): string => view('billing::admin._store-cell', ['tenant' => $row->tenant])->render())
            ->addColumn('orders', fn (OrderSettlement $row): string => e(number_format((int) $row->getAttribute('orders_count'))))
            ->addColumn('amount', fn (OrderSettlement $row): string => '<span class="font-semibold text-ink" dir="ltr">'.e(Money::ofMinor((int) $row->amount)->format()).'</span>')
            ->addColumn('gateway', fn (OrderSettlement $row): string => e(self::gatewayLabel($row->gateway, $row->method)))
            ->addColumn('status', fn (OrderSettlement $row): string => view('commerce::partials.status-badge', ['value' => (string) $row->getRawOriginal('status')])->render())
            ->addColumn('actions', fn (OrderSettlement $row): string => view('billing::finance._view-action', [
                'modal' => 'settlement-modal',
                'url' => $this->surface === 'admin' ? route('admin.finance.settlements.show', (string) $row->reference) : vendor_route('finance.settlements.show', ['reference' => (string) $row->reference]),
                'label' => __('billing::finance.settlements.view'),
            ])->render())
            ->rawColumns(['settlement', 'store', 'amount', 'status', 'actions']);
    }
}
