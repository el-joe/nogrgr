<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070a Sales (Figma 75:68061): gross sales, orders, AOV, units; sales by period
 * (gross vs platform share), sales by country, sales by store (gross, refunds, net, share).
 */
final class SalesReport extends BaseReport
{
    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->totals($filters);
        $before = $this->facts->totals($filters->previous());
        $daily = $this->facts->daily($filters);
        $gross = ReportMath::series($filters, $this->pick($daily, 'gross'));
        $countries = [];

        foreach ($this->facts->grossByCountry($filters) as $country => $amount) {
            $countries[$this->countryName($country)] = $amount;
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('gross_sales', $now['gross'], $before['gross'], 'money', 'wallet', $gross),
                $this->kpi('orders', $now['orders'], $before['orders'], 'number', 'cart', ReportMath::series($filters, $this->pick($daily, 'orders'))),
                $this->kpi('aov', ReportMath::average($now['gross'], $now['orders']), ReportMath::average($before['gross'], $before['orders']), 'money', 'tag'),
                $this->kpi('units_sold', $now['units'], $before['units'], 'number', 'box', ReportMath::series($filters, $this->pick($daily, 'units'))),
            ],
            charts: [
                $this->seriesChart('sales_by_period', 'line', $filters, [
                    ['label' => __('reporting::reports.series.gross'), 'data' => $this->dollars($gross), 'tone' => 'brand'],
                    ['label' => __('reporting::reports.series.platform'), 'data' => $this->dollars(ReportMath::series($filters, $this->pick($daily, 'platform'))), 'tone' => 'ink'],
                ]),
                $this->donut('sales_by_country', $countries, ReportFormat::money($now['gross'])),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.sales');
    }

    public function columns(ReportFilterData $filters): array
    {
        $total = $this->facts->totals($filters)['gross'];

        return [
            ReportColumn::store(__('reporting::reports.columns.store')),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('gross', __('reporting::reports.columns.gross'), 'money'),
            new ReportColumn('refunded', __('reporting::reports.columns.refunds'), 'money'),
            new ReportColumn('net', __('reporting::reports.columns.net'), 'money'),
            new ReportColumn('share', __('reporting::reports.columns.share'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->gross, $total)),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->byTenant($filters);
    }

    public function defaultOrder(): array
    {
        return [2, 'desc'];
    }
}
