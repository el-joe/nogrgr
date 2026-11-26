<?php

namespace App\Modules\Affiliates\DataTables;

use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Yajra\DataTables\EloquentDataTable;

/**
 * Payouts the affiliate received (AFF-012 history, VEN-055): reference + date, method,
 * transfer reference, amount.
 *
 * @extends BaseDataTable<AffiliatePayout>
 */
final class PortalPayoutsDataTable extends BaseDataTable
{
    private ?Affiliate $affiliate = null;

    public function __construct(private readonly PayoutRepositoryContract $payouts) {}

    public function for(Affiliate $affiliate): self
    {
        $this->affiliate = $affiliate;

        return $this;
    }

    protected function query(): Builder
    {
        if ($this->affiliate === null) {
            throw new LogicException('The payouts table needs its affiliate.');
        }

        return $this->payouts->listing(['affiliate_id' => $this->affiliate->id]);
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
            ->addColumn('method', static fn (AffiliatePayout $payout): string => '<span class="text-ink">'.e($payout->method->label()).'</span>'
                .($payout->reference !== null ? '<span class="block text-xs text-ink-secondary" dir="ltr">'.e($payout->reference).'</span>' : ''))
            ->addColumn('amount', static fn (AffiliatePayout $payout): string => '<span class="font-semibold text-ink" dir="ltr">'.e($payout->money()->format()).'</span>')
            ->rawColumns(['payout', 'method', 'amount']);
    }
}
