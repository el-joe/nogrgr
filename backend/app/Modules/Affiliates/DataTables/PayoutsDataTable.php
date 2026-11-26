<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * ADM-062 history tab: paid payouts (date, affiliate, method, reference, amount, proof).
 *
 * @extends BaseDataTable<AffiliatePayout>
 */
final class PayoutsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'method'];

    public function __construct(private readonly PayoutRepositoryContract $payouts) {}

    protected function query(): Builder
    {
        return $this->payouts->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->payouts->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';
        $locale = app()->getLocale();

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('payout', static fn (Builder $query, string $order) => $query->orderBy('paid_at', $direction($order))->orderBy('id', $direction($order)))
            ->orderColumn('amount', static fn (Builder $query, string $order) => $query->orderBy('amount', $direction($order)))
            ->addColumn('payout', static fn (AffiliatePayout $payout): string => view('affiliates::admin.partials.person', [
                'title' => $payout->displayReference(),
                'subtitle' => $payout->paid_at?->locale($locale)->translatedFormat('j M Y'),
            ])->render())
            ->addColumn('affiliate', static fn (AffiliatePayout $payout): string => view('affiliates::admin.partials.person', [
                'title' => $payout->affiliate->name ?? '—',
                'subtitle' => $payout->paid_by_name !== null ? __('affiliates::admin.payouts.paid_by', ['name' => $payout->paid_by_name]) : null,
            ])->render())
            ->addColumn('method', static fn (AffiliatePayout $payout): string => e($payout->method->label()))
            ->addColumn('reference', static fn (AffiliatePayout $payout): string => '<span dir="ltr">'.e($payout->reference ?? '—').'</span>')
            ->addColumn('amount', static fn (AffiliatePayout $payout): string => '<span class="font-semibold text-ink" dir="ltr">'.e($payout->money()->format()).'</span>')
            ->addColumn('actions', static fn (AffiliatePayout $payout): string => view('affiliates::admin.payouts._history-actions', ['payout' => $payout])->render())
            ->rawColumns(['payout', 'affiliate', 'reference', 'amount', 'actions']);
    }
}
