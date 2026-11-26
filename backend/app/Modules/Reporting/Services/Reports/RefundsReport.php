<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\AfterSalesReportRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070d Refunds & returns (old admin.orders.returns.analytics, Figma 75:69019): return
 * rate, refunded amount, top reason; refunds per period, return reasons; per store.
 */
final class RefundsReport extends BaseReport
{
    public function __construct(
        private readonly ReportFactRepositoryContract $facts,
        private readonly AfterSalesReportRepositoryContract $afterSales,
    ) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $orders = $this->facts->totals($filters)['orders'];
        $ordersBefore = $this->facts->totals($filters->previous())['orders'];
        $returns = $this->afterSales->returnTotals($filters);
        $returnsBefore = $this->afterSales->returnTotals($filters->previous());
        $refunds = $this->afterSales->refundTotals($filters);
        $refundsBefore = $this->afterSales->refundTotals($filters->previous());
        $reasons = $this->afterSales->returnsByReason($filters);
        $names = $this->afterSales->reasonNames();
        $slices = [];

        foreach ($reasons as $code => $count) {
            $slices[$names[$code] ?? $code] = $count;
        }

        arsort($slices);
        $refundSeries = ReportMath::series($filters, $this->afterSales->refundsDaily($filters));

        return new ReportSummary(
            kpis: [
                $this->kpi('return_rate', ReportMath::percent($returns['returns'], $orders), ReportMath::percent($returnsBefore['returns'], $ordersBefore), 'percent', 'return'),
                $this->kpi('refunded', $refunds['amount'], $refundsBefore['amount'], 'money', 'wallet', $refundSeries),
                $this->kpi('returns', $returns['returns'], $returnsBefore['returns'], 'number', 'box'),
                $this->kpi('seller_fault_rate', ReportMath::percent($returns['seller_fault'], $returns['returns']), ReportMath::percent($returnsBefore['seller_fault'], $returnsBefore['returns']), 'percent', 'alert-triangle'),
            ],
            tiles: [
                $this->tile('top_reason', $slices === [] ? __('reporting::reports.none') : (string) array_key_first($slices)),
                $this->tile('refunds_count', ReportFormat::number($refunds['refunds'])),
            ],
            charts: [
                $this->seriesChart('refunds_per_period', 'line', $filters, [
                    ['label' => __('reporting::reports.series.refunded'), 'data' => $this->dollars($refundSeries), 'tone' => 'danger'],
                ]),
                $this->donut('return_reasons', $slices, ReportFormat::number($returns['returns'])),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.refunds');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            ReportColumn::store(__('reporting::reports.columns.store')),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('returns_count', __('reporting::reports.columns.returns'), 'number'),
            new ReportColumn('return_rate', __('reporting::reports.columns.return_rate'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->returns_count, (int) $row->orders)),
            new ReportColumn('refunds_count', __('reporting::reports.columns.refunds_count'), 'number'),
            new ReportColumn('refunded', __('reporting::reports.columns.refunded'), 'money'),
            new ReportColumn('net', __('reporting::reports.columns.net'), 'money'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->byTenant($filters);
    }

    public function defaultOrder(): array
    {
        return [5, 'desc'];
    }
}
