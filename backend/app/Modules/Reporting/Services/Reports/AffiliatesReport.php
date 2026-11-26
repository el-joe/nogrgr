<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\AffiliateReportRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070h Affiliates (old admin.affiliates.reports, Figma 75:69998): clicks →
 * conversions → approved conversions → payouts funnel and per-affiliate performance.
 */
final class AffiliatesReport extends BaseReport
{
    public function __construct(private readonly AffiliateReportRepositoryContract $affiliates) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->affiliates->funnel($filters);
        $before = $this->affiliates->funnel($filters->previous());

        return new ReportSummary(
            kpis: [
                $this->kpi('clicks', $now['clicks'], $before['clicks'], 'number', 'link'),
                $this->kpi('conversions', $now['conversions'], $before['conversions'], 'number', 'users'),
                $this->kpi('conversion_rate', ReportMath::percent($now['conversions'], $now['clicks']), ReportMath::percent($before['conversions'], $before['clicks']), 'percent', 'percent'),
                $this->kpi('commission', $now['commission'], $before['commission'], 'money', 'gift'),
            ],
            funnel: [
                ['label' => __('reporting::reports.funnel.clicks'), 'display' => ReportFormat::number($now['clicks']), 'percent' => $now['clicks'] > 0 ? 100.0 : 0.0],
                ['label' => __('reporting::reports.funnel.conversions'), 'display' => ReportFormat::number($now['conversions']), 'percent' => ReportMath::percent($now['conversions'], $now['clicks'])],
                ['label' => __('reporting::reports.funnel.approved'), 'display' => ReportFormat::number($now['approved']), 'percent' => ReportMath::percent($now['approved'], $now['clicks'])],
                ['label' => __('reporting::reports.funnel.payouts'), 'display' => ReportFormat::money($now['payouts']), 'percent' => min(100.0, ReportMath::percent($now['payouts'], $now['commission']))],
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.affiliates');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('affiliate_name', __('reporting::reports.columns.affiliate'), 'text', true, null, static fn (object $row): ?string => $row->affiliate_email ?? null),
            new ReportColumn('clicks', __('reporting::reports.columns.clicks'), 'number'),
            new ReportColumn('conversions', __('reporting::reports.columns.conversions'), 'number'),
            new ReportColumn('conversion_rate', __('reporting::reports.columns.conversion_rate'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->conversions, (int) $row->clicks)),
            new ReportColumn('sales', __('reporting::reports.columns.referred_sales'), 'money'),
            new ReportColumn('commission', __('reporting::reports.columns.commission'), 'money'),
            new ReportColumn('paid', __('reporting::reports.columns.paid_out'), 'money'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->affiliates->byAffiliate($filters);
    }

    public function defaultOrder(): array
    {
        return [5, 'desc'];
    }
}
