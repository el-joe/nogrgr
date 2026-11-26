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
 * ADM-070f Coupons & flash-sale impact (Figma 75:69019): orders with a coupon, coupon and
 * flash-sale discounts as recorded on the orders (decision #66), revenue of promoted
 * orders; discounts per period; campaigns (coupon code × platform / store coupon).
 */
final class PromotionsReport extends BaseReport
{
    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->totals($filters);
        $before = $this->facts->totals($filters->previous());
        $daily = $this->facts->daily($filters);

        return new ReportSummary(
            kpis: [
                $this->kpi('coupon_orders', $now['coupon_orders'], $before['coupon_orders'], 'number', 'tag'),
                $this->kpi('coupon_discount', $now['coupon'], $before['coupon'], 'money', 'percent'),
                $this->kpi('flash_discount', $now['flash'], $before['flash'], 'money', 'zap'),
                $this->kpi('promo_revenue', $now['promo_revenue'], $before['promo_revenue'], 'money', 'wallet'),
            ],
            tiles: [
                $this->tile('promo_share', ReportFormat::percent(ReportMath::percent($now['promo_revenue'], $now['gross']))),
                $this->tile('flash_orders', ReportFormat::number($now['flash_orders'])),
            ],
            charts: [
                $this->seriesChart('discounts_per_period', 'line', $filters, [
                    ['label' => __('reporting::reports.series.coupon'), 'data' => $this->dollars(ReportMath::series($filters, $this->pick($daily, 'coupon'))), 'tone' => 'brand'],
                    ['label' => __('reporting::reports.series.flash'), 'data' => $this->dollars(ReportMath::series($filters, $this->pick($daily, 'flash'))), 'tone' => 'ink'],
                ]),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.promotions');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('code', __('reporting::reports.columns.campaign'), 'text', true, static fn (object $row): string => $row->code !== '' ? (string) $row->code : __('reporting::reports.unnamed_coupon'),
                static fn (object $row): string => __('reporting::reports.coupon_source.'.($row->source === 'vendor' ? 'vendor' : 'central'))),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('discount', __('reporting::reports.columns.discount'), 'money'),
            new ReportColumn('revenue', __('reporting::reports.columns.revenue'), 'money'),
            new ReportColumn('stores', __('reporting::reports.columns.stores'), 'number'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->coupons($filters);
    }
}
