<?php

namespace App\Modules\Reporting\DataTables;

use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Orders of one store on the vendor profile "Stats & orders" tab (ADM-041e): order,
 * customer, date, items, total, status; status / date filters and order search.
 *
 * @extends BaseDataTable<OrderFact>
 */
final class VendorOrdersDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'from', 'to'];

    private int $tenantId = 0;

    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->facts->ordersOf($this->tenantId);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $status = is_string($filters['status'] ?? null) && $filters['status'] !== '' ? $filters['status'] : null;
        $from = is_string($filters['from'] ?? null) && strtotime($filters['from']) !== false ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) && strtotime($filters['to']) !== false ? $filters['to'] : null;

        return $query
            ->when($status !== null, static fn (Builder $inner) => $inner->where('status', $status))
            ->when($from !== null, static fn (Builder $inner) => $inner->whereDate('placed_at', '>=', (string) $from))
            ->when($to !== null, static fn (Builder $inner) => $inner->whereDate('placed_at', '<=', (string) $to))
            ->when($search !== '', static function (Builder $inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $inner->where(static fn (Builder $where) => $where->where('order_number', 'like', $like)->orWhere('customer_name', 'like', $like));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('order_number', $direction($order)))
            ->orderColumn('date', static fn (Builder $query, string $order) => $query->orderBy('placed_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('total', static fn (Builder $query, string $order) => $query->orderBy('grand_total', $direction($order)))
            ->addColumn('order', static fn (OrderFact $fact): string => '<span class="font-medium text-ink" dir="ltr">#'.e($fact->order_number).'</span>')
            ->addColumn('customer', static fn (OrderFact $fact): string => e($fact->customer_name ?: __('reporting::shoppers.guest')))
            ->addColumn('date', static fn (OrderFact $fact): string => '<span class="whitespace-nowrap" dir="ltr">'.e((string) $fact->placed_at?->locale(app()->getLocale())->translatedFormat('j M Y H:i')).'</span>')
            ->addColumn('items', static fn (OrderFact $fact): string => '<span class="tabular-nums" dir="ltr">'.e(ReportFormat::number($fact->items_count)).'</span>')
            ->addColumn('total', static fn (OrderFact $fact): string => '<span class="font-semibold text-ink" dir="ltr">'.e(ReportFormat::money($fact->grand_total)).'</span>')
            ->addColumn('status_badge', static fn (OrderFact $fact): string => view('reporting::admin.vendor-stats._order-status', ['status' => $fact->status])->render())
            ->rawColumns(['order', 'date', 'items', 'total', 'status_badge']);
    }
}
