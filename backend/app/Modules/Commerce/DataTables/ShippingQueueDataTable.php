<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Sales › Shipping queue (ADM-022, Figma 70:34839): paid catalog orders by tab
 * (to ship / in transit / delayed / delivered today) — order + items / weight, customer,
 * company + AWB, branch, ship-by, shipping status. Own-products orders never appear.
 *
 * @extends BaseDataTable<OrderFact>
 */
final class ShippingQueueDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'tab', 'company_id', 'branch_id', 'country_id', 'fulfilment_status'];

    /**
     * @param  array<int, string>  $branches  id => name
     */
    public function __construct(private readonly OrderFactRepositoryContract $facts, private array $branches = []) {}

    /**
     * @param  array<int, string>  $branches
     */
    public function withBranches(array $branches): self
    {
        $this->branches = $branches;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->facts->query();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->facts->shippingQueue($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('order', static fn (Builder $query, string $order) => $query->orderBy('placed_at', $direction($order)))
            ->orderColumn('customer', static fn (Builder $query, string $order) => $query->orderBy('customer_name', $direction($order)))
            ->orderColumn('company', static fn (Builder $query, string $order) => $query->orderBy('shipping_company_name', $direction($order)))
            ->orderColumn('branch', static fn (Builder $query, string $order) => $query->orderBy('fulfilment_branch_id', $direction($order)))
            ->orderColumn('ship_by', static fn (Builder $query, string $order) => $query->orderBy('delivery_min_date', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('fulfilment_status', $direction($order)))
            ->addColumn('select', fn (OrderFact $fact): string => '<input type="checkbox" class="ui-checkbox" name="ids[]" value="'.$fact->id.'" form="shipping-bulk-form" aria-label="'.e(__('commerce::fulfilment.queue.select', ['number' => $fact->order_number])).'" data-row-select>')
            ->addColumn('order', fn (OrderFact $fact): string => view('commerce::admin.shipping._order-cell', ['fact' => $fact])->render())
            ->addColumn('customer', fn (OrderFact $fact): string => '<span class="block font-medium text-ink">'.e((string) $fact->customer_name).'</span><span class="text-xs text-ink-secondary">'.e((string) $fact->customer_city).'</span>')
            ->addColumn('company', fn (OrderFact $fact): string => '<span class="block font-medium text-ink">'.e((string) ($fact->shipping_company_name ?? '—')).'</span><span class="text-xs text-ink-secondary" dir="ltr">'.e($fact->tracking_number ?? __('commerce::fulfilment.queue.awb_pending')).'</span>')
            ->addColumn('branch', fn (OrderFact $fact): string => e($fact->fulfilment_branch_id !== null ? ($this->branches[$fact->fulfilment_branch_id] ?? '—') : '—'))
            ->addColumn('ship_by', fn (OrderFact $fact): string => e($this->shipBy($fact)))
            ->addColumn('status', fn (OrderFact $fact): string => view('commerce::partials.status-badge', ['value' => FulfilmentStatus::tryFrom($fact->fulfilment_status) ?? $fact->fulfilment_status])->render())
            ->addColumn('actions', fn (OrderFact $fact): string => view('commerce::admin.shipping._actions-cell', ['fact' => $fact])->render())
            ->rawColumns(['select', 'order', 'customer', 'company', 'status', 'actions']);
    }

    private function shipBy(OrderFact $fact): string
    {
        if (! in_array($fact->fulfilment_status, ['pending', 'packed'], true) || $fact->delivery_min_date === null) {
            return '—';
        }

        $date = $fact->delivery_min_date->copy()->subDay();

        return match (true) {
            $date->isToday() || $date->isPast() => (string) __('commerce::fulfilment.queue.today'),
            $date->isTomorrow() => (string) __('commerce::fulfilment.queue.tomorrow'),
            default => $date->translatedFormat('j M'),
        };
    }
}
