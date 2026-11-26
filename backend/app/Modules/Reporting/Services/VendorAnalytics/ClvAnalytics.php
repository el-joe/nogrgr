<?php

namespace App\Modules\Reporting\Services\VendorAnalytics;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * VEN-045c Customer lifetime value (old tenant.analytics.clv.data, Figma 12:16815): lifetime
 * = every booked order placed up to the period end. Customers (anyone who placed an order),
 * buyers, repeat buyers, average lifetime spend; top spenders, buyer mix, momentum; the
 * customer ranking table.
 */
final class ClvAnalytics extends VendorReport
{
    private const TOP = 8;

    public function __construct(
        private readonly VendorAnalyticsRepositoryContract $analytics,
        private readonly ReportFactRepositoryContract $facts,
    ) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->analytics->customerTotals($filters);
        $before = $this->analytics->customerTotals($filters->previous());
        $mix = $this->analytics->buyerMix($filters);
        $newDaily = $this->facts->newCustomersDaily($filters);
        $repeatDaily = [];

        foreach ($this->facts->buyersDaily($filters) as $day => $buyers) {
            $repeatDaily[$day] = max(0, $buyers - ($newDaily[$day] ?? 0));
        }

        $top = $this->analytics->customers($filters)->orderByDesc('spent')->orderBy('ckey')->limit(self::TOP)->get();
        $max = (int) ($top->first()->spent ?? 0);
        $leaders = [];

        foreach ($top->values() as $index => $row) {
            $leaders[] = [
                'rank' => $index + 1,
                'name' => (string) ($row->customer_name ?: $row->customer_email ?: __('reporting::vendor_analytics.guest')),
                'slug' => (string) ($row->customer_email ?? ''),
                'value' => ReportFormat::money((int) $row->spent),
                'detail' => trans_choice('reporting::vendor_analytics.orders_count', (int) $row->orders, ['count' => ReportFormat::number((int) $row->orders)]),
                'percent' => ReportMath::percent((int) $row->spent, $max),
            ];
        }

        $slices = [];

        foreach ($mix as $key => $count) {
            $slices[__('reporting::vendor_analytics.mix.'.$key)] = $count;
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('customers', $now['customers'], $before['customers'], 'number', 'users'),
                $this->kpi('buyers', $now['buyers'], $before['buyers'], 'number', 'check-circle'),
                $this->kpi('repeat_buyers', $now['repeat'], $before['repeat'], 'number', 'cart'),
                $this->kpi('avg_lifetime', ReportMath::average($now['spent'], $now['buyers']), ReportMath::average($before['spent'], $before['buyers']), 'money', 'wallet'),
            ],
            charts: [
                $this->donut('buyer_mix', $slices, ReportFormat::number($now['buyers'])),
                $this->seriesChart('clv_momentum', 'line', $filters, [
                    ['label' => __('reporting::vendor_analytics.series.new_customers'), 'data' => ReportMath::series($filters, $newDaily), 'tone' => 'info'],
                    ['label' => __('reporting::vendor_analytics.series.repeat_buyers'), 'data' => ReportMath::series($filters, $repeatDaily), 'tone' => 'brand'],
                ]),
            ],
            leaders: $leaders,
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::vendor_analytics.tables.clv');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('customer_name', __('reporting::vendor_analytics.columns.customer'), 'text', true,
                static fn (object $row): string => (string) ($row->customer_name ?: __('reporting::vendor_analytics.guest')),
                static fn (object $row): ?string => $row->customer_email !== null ? (string) $row->customer_email : null),
            new ReportColumn('orders', __('reporting::vendor_analytics.columns.orders'), 'number'),
            new ReportColumn('paid', __('reporting::vendor_analytics.columns.paid'), 'number'),
            new ReportColumn('spent', __('reporting::vendor_analytics.columns.total_spend'), 'money'),
            new ReportColumn('collected', __('reporting::vendor_analytics.columns.collected'), 'money'),
            new ReportColumn('avg_order', __('reporting::vendor_analytics.columns.avg_order'), 'money'),
            new ReportColumn('last_order_at', __('reporting::vendor_analytics.columns.last_order'), 'text', true, fn (object $row): string => $this->date($row->last_order_at)),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->analytics->customers($filters);
    }

    public function defaultOrder(): array
    {
        return [3, 'desc'];
    }
}
