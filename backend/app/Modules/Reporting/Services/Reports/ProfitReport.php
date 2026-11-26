<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\SubscriptionReportRepositoryContract;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070c Platform vs vendor profit (old admin.finance.reports, Figma 75:69019): the
 * platform / vendor split recorded on every order by Commerce\Pricing (ORDER_FORMULAS
 * §3, never recomputed here) plus paid subscription invoices; profit split per period and
 * per store.
 */
final class ProfitReport extends BaseReport
{
    public function __construct(
        private readonly ReportFactRepositoryContract $facts,
        private readonly SubscriptionReportRepositoryContract $subscriptions,
    ) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->totals($filters);
        $before = $this->facts->totals($filters->previous());
        $invoices = $this->subscriptions->paidInvoices($filters)['amount'];
        $invoicesBefore = $this->subscriptions->paidInvoices($filters->previous())['amount'];
        $daily = $this->facts->daily($filters);
        $platform = ReportMath::series($filters, $this->pick($daily, 'platform'));

        return new ReportSummary(
            kpis: [
                $this->kpi('platform_profit', $now['platform'], $before['platform'], 'money', 'chart', $platform),
                $this->kpi('vendor_profit', $now['vendor'], $before['vendor'], 'money', 'store'),
                $this->kpi('subscription_revenue', $invoices, $invoicesBefore, 'money', 'credit-card'),
                $this->kpi('platform_share', ReportMath::percent($now['platform'], $now['gross']), ReportMath::percent($before['platform'], $before['gross']), 'percent', 'percent'),
            ],
            charts: [
                $this->seriesChart('profit_split', 'line', $filters, [
                    ['label' => __('reporting::reports.series.platform'), 'data' => $this->dollars($platform), 'tone' => 'brand'],
                    ['label' => __('reporting::reports.series.vendor'), 'data' => $this->dollars(ReportMath::series($filters, $this->pick($daily, 'vendor'))), 'tone' => 'ink'],
                ]),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.profit');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            ReportColumn::store(__('reporting::reports.columns.store')),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('gross', __('reporting::reports.columns.gross'), 'money'),
            new ReportColumn('platform', __('reporting::reports.columns.platform_profit'), 'money'),
            new ReportColumn('vendor', __('reporting::reports.columns.vendor_profit'), 'money'),
            new ReportColumn('platform_share', __('reporting::reports.columns.platform_share'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->platform, (int) $row->gross)),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->byTenant($filters);
    }

    public function defaultOrder(): array
    {
        return [3, 'desc'];
    }
}
