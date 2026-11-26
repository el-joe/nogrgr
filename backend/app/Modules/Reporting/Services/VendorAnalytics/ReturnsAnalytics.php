<?php

namespace App\Modules\Reporting\Services\VendorAnalytics;

use App\Modules\AfterSales\Enums\RefundSource;
use App\Modules\AfterSales\Enums\RefundStatus;
use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\AfterSalesReportRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * VEN-024 Return analytics (old tenant.returns.analytics, Figma 84:105181): return rate
 * (return requests ÷ booked orders of the window), refunded (completed refunds processed in
 * the window), exchanges, average resolution days (opened → refunded / exchanged / closed);
 * return reasons, refunds over time; refunds requested in the window.
 */
final class ReturnsAnalytics extends VendorReport
{
    public function __construct(
        private readonly VendorAnalyticsRepositoryContract $analytics,
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
        $stats = $this->analytics->returnStats($filters);
        $statsBefore = $this->analytics->returnStats($filters->previous());
        $names = $this->afterSales->reasonNames();
        $slices = [];

        foreach ($this->afterSales->returnsByReason($filters) as $code => $count) {
            $slices[$names[$code] ?? $code] = $count;
        }

        $refundSeries = ReportMath::series($filters, $this->afterSales->refundsDaily($filters));

        return new ReportSummary(
            kpis: [
                $this->kpi('return_rate', ReportMath::percent($returns['returns'], $orders), ReportMath::percent($returnsBefore['returns'], $ordersBefore), 'percent', 'return'),
                $this->kpi('refunded', $refunds['amount'], $refundsBefore['amount'], 'money', 'wallet', $refundSeries),
                $this->kpi('exchanges', $stats['exchanges'], $statsBefore['exchanges'], 'number', 'refresh'),
                $this->kpi('avg_resolution_days', $stats['avg_resolution_days'], $statsBefore['avg_resolution_days'], 'decimal', 'clock'),
            ],
            charts: [
                $this->donut('return_reasons', $slices, ReportFormat::number($returns['returns'])),
                $this->seriesChart('refunds_per_period', 'line', $filters, [
                    ['label' => __('reporting::vendor_analytics.series.refunded'), 'data' => $this->dollars($refundSeries), 'tone' => 'danger'],
                ]),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::vendor_analytics.tables.returns');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('reference', __('reporting::vendor_analytics.columns.refund'), 'text', true, null,
                static fn (object $row): string => $row->return_reference !== null ? (string) $row->return_reference : (RefundSource::tryFrom((string) $row->source)?->label() ?? (string) $row->source)),
            new ReportColumn('order_number', __('reporting::vendor_analytics.columns.order'), 'text'),
            new ReportColumn('amount', __('reporting::vendor_analytics.columns.amount'), 'money'),
            new ReportColumn('status', __('reporting::vendor_analytics.columns.status'), 'text', true, static fn (object $row): string => RefundStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status),
            new ReportColumn('requested_at', __('reporting::vendor_analytics.columns.requested'), 'text', true, fn (object $row): string => $this->date($row->requested_at)),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->analytics->refunds($filters);
    }

    public function defaultOrder(): array
    {
        return [4, 'desc'];
    }
}
