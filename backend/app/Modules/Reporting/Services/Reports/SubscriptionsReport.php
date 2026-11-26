<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\SubscriptionReportRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\SubscriptionMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * ADM-070g Subscriptions · MRR · churn · trials (Figma 75:69998), from the central
 * `subscriptions` lifecycle (Support\SubscriptionMetrics) and paid invoices.
 */
final class SubscriptionsReport extends BaseReport
{
    /** @var array<string, Collection<int, object>> */
    private array $lifecycles = [];

    public function __construct(private readonly SubscriptionReportRepositoryContract $subscriptions) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $rows = $this->lifecycles($filters);
        $previous = $filters->previous();
        $start = $filters->start();
        $end = $filters->end();
        $mrrSeries = [];

        foreach ($filters->bucketKeys() as $key) {
            $bucketEnd = strlen($key) === 7 ? CarbonImmutable::parse($key.'-01')->endOfMonth() : CarbonImmutable::parse($key)->endOfDay();
            $mrrSeries[] = SubscriptionMetrics::mrr($rows, $bucketEnd->min($end));
        }

        $invoices = $this->subscriptions->paidInvoices($filters);

        return new ReportSummary(
            kpis: [
                $this->kpi('mrr', SubscriptionMetrics::mrr($rows, $end), SubscriptionMetrics::mrr($rows, $previous->end()), 'money', 'wallet', $mrrSeries),
                $this->kpi('churn_rate', SubscriptionMetrics::churnRate($rows, $start, $end), SubscriptionMetrics::churnRate($rows, $previous->start(), $previous->end()), 'percent', 'arrow-down'),
                $this->kpi('trials', SubscriptionMetrics::trials($rows, $end), SubscriptionMetrics::trials($rows, $previous->end()), 'number', 'timer'),
                $this->kpi('new_subscriptions', SubscriptionMetrics::started($rows, $start, $end), SubscriptionMetrics::started($rows, $previous->start(), $previous->end()), 'number', 'plus'),
            ],
            tiles: [
                $this->tile('invoice_revenue', ReportFormat::money($invoices['amount'])),
                $this->tile('arr', ReportFormat::money(SubscriptionMetrics::mrr($rows, $end) * 12)),
            ],
            charts: [
                $this->seriesChart('mrr', 'line', $filters, [
                    ['label' => __('reporting::reports.series.mrr'), 'data' => $this->dollars($mrrSeries), 'tone' => 'brand'],
                ]),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.subscriptions');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('plan', __('reporting::reports.columns.plan')),
            new ReportColumn('active', __('reporting::reports.columns.active'), 'number'),
            new ReportColumn('trials', __('reporting::reports.columns.trials'), 'number'),
            new ReportColumn('started', __('reporting::reports.columns.started'), 'number'),
            new ReportColumn('churned', __('reporting::reports.columns.churned'), 'number'),
            new ReportColumn('mrr', __('reporting::reports.columns.mrr'), 'money'),
        ];
    }

    public function rows(ReportFilterData $filters): Collection
    {
        return SubscriptionMetrics::byPlan($this->lifecycles($filters), $filters->start(), $filters->end(), __('reporting::reports.no_plan'));
    }

    public function defaultOrder(): array
    {
        return [5, 'desc'];
    }

    /**
     * @return Collection<int, object>
     */
    private function lifecycles(ReportFilterData $filters): Collection
    {
        return $this->lifecycles[md5(serialize($filters->query()))] ??= $this->subscriptions->lifecycles($filters);
    }
}
