<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070k Customers — new vs returning (Figma 75:69998). A buyer is new when their first
 * booked order in that store falls inside the window, returning otherwise; buyers are the
 * store's customer id or, for guests, their e-mail.
 */
final class CustomersReport extends BaseReport
{
    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->customerTotals($filters);
        $before = $this->facts->customerTotals($filters->previous());
        $new = $this->facts->newCustomersDaily($filters);
        $buyers = $this->facts->buyersDaily($filters);
        $returning = [];

        foreach ($buyers as $day => $count) {
            $returning[$day] = max(0, $count - ($new[$day] ?? 0));
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('customers', $now['customers'], $before['customers'], 'number', 'users'),
                $this->kpi('new_customers', $now['new'], $before['new'], 'number', 'plus', ReportMath::series($filters, $new)),
                $this->kpi('returning_customers', $now['returning'], $before['returning'], 'number', 'refresh'),
                $this->kpi('returning_rate', ReportMath::percent($now['returning'], $now['customers']), ReportMath::percent($before['returning'], $before['customers']), 'percent', 'percent'),
            ],
            charts: [
                $this->seriesChart('new_vs_returning', 'bar', $filters, [
                    ['label' => __('reporting::reports.series.new'), 'data' => ReportMath::series($filters, $new), 'tone' => 'brand'],
                    ['label' => __('reporting::reports.series.returning'), 'data' => ReportMath::series($filters, $returning), 'tone' => 'ink'],
                ]),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.customers');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            ReportColumn::store(__('reporting::reports.columns.store')),
            new ReportColumn('customers', __('reporting::reports.columns.customers'), 'number'),
            new ReportColumn('new_customers', __('reporting::reports.columns.new'), 'number'),
            new ReportColumn('returning_customers', __('reporting::reports.columns.returning'), 'number'),
            new ReportColumn('returning_rate', __('reporting::reports.columns.returning_rate'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->returning_customers, (int) $row->customers)),
            new ReportColumn('orders_per_customer', __('reporting::reports.columns.orders_per_customer'), 'decimal', false, static fn (object $row): float => (int) $row->customers > 0 ? round((int) $row->orders / (int) $row->customers, 1) : 0.0),
            new ReportColumn('spent', __('reporting::reports.columns.spent'), 'money'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->customersByTenant($filters);
    }
}
