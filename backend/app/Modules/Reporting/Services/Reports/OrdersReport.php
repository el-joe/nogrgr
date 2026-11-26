<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070b Orders report (old admin.orders.report, Figma 75:69019): orders, cancelled
 * rate, average items, delivered; orders per period, orders by status; monthly series
 * per store.
 */
final class OrdersReport extends BaseReport
{
    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->stats($filters);
        $before = $this->stats($filters->previous());
        $daily = $this->facts->daily($filters);
        $orders = ReportMath::series($filters, $this->pick($daily, 'orders'));
        $statuses = [];

        foreach ($this->facts->statusCounts($filters) as $status => $count) {
            $statuses[OrderStatus::tryFrom($status)?->label() ?? $status] = $count;
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('orders', $now['orders'], $before['orders'], 'number', 'cart', $orders),
                $this->kpi('cancelled_rate', $now['cancelled_rate'], $before['cancelled_rate'], 'percent', 'x'),
                $this->kpi('avg_items', $now['avg_items'], $before['avg_items'], 'decimal', 'box'),
                $this->kpi('delivered', $now['delivered'], $before['delivered'], 'number', 'check-circle'),
            ],
            charts: [
                $this->seriesChart('orders_per_period', 'bar', $filters, [
                    ['label' => __('reporting::reports.series.orders'), 'data' => $orders, 'tone' => 'brand'],
                ]),
                $this->donut('orders_by_status', $statuses, ReportFormat::number(array_sum($statuses))),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.orders');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('month', __('reporting::reports.columns.month'), 'month'),
            ReportColumn::store(__('reporting::reports.columns.store')),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('delivered', __('reporting::reports.columns.delivered'), 'number'),
            new ReportColumn('cancelled', __('reporting::reports.columns.cancelled'), 'number'),
            new ReportColumn('units', __('reporting::reports.columns.units'), 'number'),
            new ReportColumn('gross', __('reporting::reports.columns.gross'), 'money'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->monthlyByTenant($filters);
    }

    public function defaultOrder(): array
    {
        return [0, 'desc'];
    }

    /**
     * @return array{orders: int, cancelled_rate: float, avg_items: float, delivered: int}
     */
    private function stats(ReportFilterData $filters): array
    {
        $statuses = $this->facts->statusCounts($filters);
        $totals = $this->facts->totals($filters);
        $placed = array_sum($statuses);

        return [
            'orders' => $totals['orders'],
            'cancelled_rate' => ReportMath::percent($statuses['cancelled'] ?? 0, $placed),
            'avg_items' => $totals['orders'] > 0 ? round($totals['units'] / $totals['orders'], 1) : 0.0,
            'delivered' => ($statuses['delivered'] ?? 0) + ($statuses['completed'] ?? 0),
        ];
    }
}
