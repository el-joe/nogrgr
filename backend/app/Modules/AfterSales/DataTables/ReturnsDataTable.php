<?php

namespace App\Modules\AfterSales\DataTables;

use App\Modules\AfterSales\Models\ReturnRequest;
use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Modules\AfterSales\Services\ReturnPolicyService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Returns list (ADM-023 all stores / VEN-022 current store through the tenant scope):
 * reference + date, order, store, item × qty, reason, value, status. Filters: tab,
 * status, type, store (admin), search.
 *
 * @extends BaseDataTable<ReturnRequest>
 */
final class ReturnsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'tab', 'status', 'type', 'tenant_id'];

    private string $surface = 'admin';

    public function __construct(private readonly ReturnRequestRepositoryContract $returns) {}

    public function forSurface(string $surface): self
    {
        $this->surface = $surface;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->returns->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->returns->listing($filters)->with('tenant');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('return', static fn (Builder $query, string $order) => $query->orderBy('id', $direction($order)))
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('order_number', $direction($order)))
            ->orderColumn('value', static fn (Builder $query, string $order) => $query->orderBy('refund_amount', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $direction($order)))
            ->addColumn('return', fn (ReturnRequest $return): string => view('after-sales::partials._return-cell', ['return' => $return, 'url' => $this->url($return)])->render())
            ->addColumn('order', fn (ReturnRequest $return): string => '<span class="font-medium text-ink" dir="ltr">#'.e($return->order_number).'</span><span class="block text-xs text-ink-secondary">'.e((string) $return->customer_name).'</span>')
            ->addColumn('store', fn (ReturnRequest $return): string => e((string) $return->tenant?->getAttribute('name')))
            ->addColumn('item', fn (ReturnRequest $return): string => '<span class="block text-ink">'.e($return->product_name).'</span><span class="text-xs text-ink-secondary">'.e(trim(($return->variant_label ? $return->variant_label.' · ' : '').'× '.$return->quantity)).'</span>')
            ->addColumn('reason', fn (ReturnRequest $return): string => e($this->reason($return)))
            ->addColumn('value', fn (ReturnRequest $return): string => '<span class="font-semibold text-ink" dir="ltr">'.e($return->money('refund_amount')->format()).'</span>')
            ->addColumn('status', fn (ReturnRequest $return): string => view('commerce::partials.status-badge', ['value' => $return->status])->render())
            ->addColumn('actions', fn (ReturnRequest $return): string => view('after-sales::partials._view-action', ['url' => $this->url($return), 'label' => __('after-sales::returns.view', ['reference' => $return->reference])])->render())
            ->rawColumns(['return', 'order', 'item', 'value', 'status', 'actions']);
    }

    private function url(ReturnRequest $return): string
    {
        return $this->surface === 'vendor' ? vendor_route('sales.returns.show', ['return' => $return->id]) : route('admin.returns.show', $return->id);
    }

    private function reason(ReturnRequest $return): string
    {
        static $labels = null;
        $labels ??= app(ReturnPolicyService::class)->reasons()->mapWithKeys(static fn ($reason): array => [$reason->code => $reason->name])->all();

        return (string) ($labels[$return->reason_code] ?? $return->reason_code);
    }
}
