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
 * ADM-070i Vendor leaderboard (Figma 75:69998): stores ranked by gross sales, with
 * orders, platform profit, AOV and refund rate.
 */
final class VendorsReport extends BaseReport
{
    public const LEADERS = 5;

    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->totals($filters);
        $before = $this->facts->totals($filters->previous());
        $stores = $this->storeCount($filters);
        $storesBefore = $this->storeCount($filters->previous());
        $top = $this->facts->byTenant($filters)->orderByDesc('gross')->limit(self::LEADERS)->get();
        $leaders = [];

        foreach ($top->values() as $index => $row) {
            $leaders[] = [
                'rank' => $index + 1,
                'name' => (string) $row->tenant_name,
                'slug' => (string) $row->tenant_slug,
                'value' => ReportFormat::money((int) $row->gross),
                'detail' => trans_choice('reporting::reports.orders_count', (int) $row->orders, ['count' => ReportFormat::number((int) $row->orders)]),
                'percent' => ReportMath::percent((int) $row->gross, (int) ($top->first()->gross ?? 0)),
            ];
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('selling_stores', $stores, $storesBefore, 'number', 'store'),
                $this->kpi('gross_sales', $now['gross'], $before['gross'], 'money', 'wallet'),
                $this->kpi('avg_per_store', ReportMath::average($now['gross'], $stores), ReportMath::average($before['gross'], $storesBefore), 'money', 'chart'),
                $this->kpi('top_store_share', ReportMath::percent((int) ($top->first()->gross ?? 0), $now['gross']), 0, 'percent', 'award'),
            ],
            leaders: $leaders,
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.vendors');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            ReportColumn::store(__('reporting::reports.columns.store')),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('gross', __('reporting::reports.columns.gross'), 'money'),
            new ReportColumn('platform', __('reporting::reports.columns.platform_profit'), 'money'),
            new ReportColumn('aov', __('reporting::reports.columns.aov'), 'money', false, static fn (object $row): int => ReportMath::average((int) $row->gross, (int) $row->orders)),
            new ReportColumn('refund_rate', __('reporting::reports.columns.refund_rate'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->refunded, (int) $row->gross)),
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

    private function storeCount(ReportFilterData $filters): int
    {
        return $this->facts->byTenant($filters)->count();
    }
}
