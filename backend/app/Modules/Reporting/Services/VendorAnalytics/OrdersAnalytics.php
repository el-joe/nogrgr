<?php

namespace App\Modules\Reporting\Services\VendorAnalytics;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * VEN-045a Order analytics (old tenant.analytics.orders.data.monthly / .status, Figma
 * 12:16223): gross sales, collected (paid), outstanding (booked, not yet paid — COD),
 * average order; gross vs collected, status mix, customer momentum, status proportions;
 * monthly performance table.
 */
final class OrdersAnalytics extends VendorReport
{
    public function __construct(
        private readonly VendorAnalyticsRepositoryContract $analytics,
        private readonly ReportFactRepositoryContract $facts,
    ) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->analytics->orderTotals($filters);
        $before = $this->analytics->orderTotals($filters->previous());
        $gross = ReportMath::series($filters, $this->pick($this->facts->daily($filters), 'gross'));
        $collected = ReportMath::series($filters, $this->analytics->collectedDaily($filters));
        $newDaily = $this->facts->newCustomersDaily($filters);
        $repeatDaily = [];

        foreach ($this->facts->buyersDaily($filters) as $day => $buyers) {
            $repeatDaily[$day] = max(0, $buyers - ($newDaily[$day] ?? 0));
        }

        $statuses = [];

        foreach ($this->facts->statusCounts($filters) as $status => $count) {
            $statuses[OrderStatus::tryFrom($status)?->label() ?? $status] = $count;
        }

        arsort($statuses);
        $placed = array_sum($statuses);
        $funnel = [];

        foreach ($statuses as $label => $count) {
            $percent = ReportMath::percent($count, $placed);
            $funnel[] = ['label' => (string) $label, 'display' => ReportFormat::number($count).' · '.ReportFormat::percent($percent), 'percent' => $percent];
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('gross', $now['gross'], $before['gross'], 'money', 'chart', $gross),
                $this->kpi('collected', $now['collected'], $before['collected'], 'money', 'wallet', $collected),
                $this->kpi('outstanding', $now['outstanding'], $before['outstanding'], 'money', 'clock'),
                $this->kpi('avg_order', ReportMath::average($now['gross'], $now['orders']), ReportMath::average($before['gross'], $before['orders']), 'money', 'cart'),
            ],
            charts: [
                $this->seriesChart('gross_vs_collected', 'line', $filters, [
                    ['label' => __('reporting::vendor_analytics.series.gross'), 'data' => $this->dollars($gross), 'tone' => 'info'],
                    ['label' => __('reporting::vendor_analytics.series.collected'), 'data' => $this->dollars($collected), 'tone' => 'success'],
                ]),
                $this->donut('status_mix', $statuses, ReportFormat::number($placed)),
                $this->seriesChart('customer_momentum', 'bar', $filters, [
                    ['label' => __('reporting::vendor_analytics.series.new_customers'), 'data' => ReportMath::series($filters, $newDaily), 'tone' => 'brand'],
                    ['label' => __('reporting::vendor_analytics.series.repeat_buyers'), 'data' => ReportMath::series($filters, $repeatDaily), 'tone' => 'success'],
                ], false),
            ],
            funnel: $funnel,
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::vendor_analytics.tables.orders');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('month', __('reporting::vendor_analytics.columns.month'), 'month'),
            new ReportColumn('orders', __('reporting::vendor_analytics.columns.orders'), 'number'),
            new ReportColumn('paid', __('reporting::vendor_analytics.columns.paid'), 'number'),
            new ReportColumn('gross', __('reporting::vendor_analytics.columns.gross'), 'money'),
            new ReportColumn('collected', __('reporting::vendor_analytics.columns.collected'), 'money'),
            new ReportColumn('avg_order', __('reporting::vendor_analytics.columns.avg_order'), 'money'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->analytics->monthly($filters);
    }

    public function defaultOrder(): array
    {
        return [0, 'desc'];
    }
}
