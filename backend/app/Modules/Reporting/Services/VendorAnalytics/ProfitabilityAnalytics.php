<?php

namespace App\Modules\Reporting\Services\VendorAnalytics;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * VEN-045d Product profitability (old tenant.analytics.profitability.data, Figma 87:108835):
 * per product of paid orders — units, revenue (line grand totals), profit (the store's share
 * of the lines, PricedLine::vendorShare as recorded in `product_daily_facts`) and margin.
 */
final class ProfitabilityAnalytics extends VendorReport
{
    private const TOP = 8;

    public function __construct(private readonly VendorAnalyticsRepositoryContract $analytics) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->analytics->productTotals($filters);
        $before = $this->analytics->productTotals($filters->previous());
        $top = [];

        foreach ($this->analytics->products($filters)->orderByDesc('profit')->orderBy('store_product_id')->limit(self::TOP)->get() as $row) {
            $name = (string) ($row->product_name ?: '#'.$row->store_product_id);
            $top[$name] = round((int) $row->profit / 100, 2);
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('revenue', $now['revenue'], $before['revenue'], 'money', 'chart'),
                $this->kpi('profit', $now['profit'], $before['profit'], 'money', 'wallet'),
                $this->kpi('margin', ReportMath::percent($now['profit'], $now['revenue']), ReportMath::percent($before['profit'], $before['revenue']), 'percent', 'percent'),
                $this->kpi('units', $now['units'], $before['units'], 'number', 'box'),
            ],
            charts: [
                [...$this->categoryChart('profit_by_product', $top, __('reporting::vendor_analytics.series.profit')), 'wide' => true],
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::vendor_analytics.tables.profitability');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('product_name', __('reporting::vendor_analytics.columns.product'), 'text', true, static fn (object $row): string => (string) ($row->product_name ?: '#'.$row->store_product_id)),
            new ReportColumn('units', __('reporting::vendor_analytics.columns.units'), 'number'),
            new ReportColumn('revenue', __('reporting::vendor_analytics.columns.revenue'), 'money'),
            new ReportColumn('profit', __('reporting::vendor_analytics.columns.profit'), 'money'),
            new ReportColumn('margin', __('reporting::vendor_analytics.columns.margin'), 'percent', true, static fn (object $row): float => (float) $row->margin),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->analytics->products($filters);
    }

    public function defaultOrder(): array
    {
        return [3, 'desc'];
    }
}
