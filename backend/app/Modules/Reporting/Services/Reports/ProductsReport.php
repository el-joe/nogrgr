<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ProductActivityRepositoryContract;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070j Product performance (Figma 75:69998): product page views, add-to-cart and paid
 * orders per store product from `product_daily_facts`; conversion = paid orders / views.
 */
final class ProductsReport extends BaseReport
{
    public function __construct(private readonly ProductActivityRepositoryContract $products) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->products->totals($filters);
        $before = $this->products->totals($filters->previous());
        $daily = $this->products->daily($filters);
        $views = ReportMath::series($filters, $this->pick($daily, 'views'));

        return new ReportSummary(
            kpis: [
                $this->kpi('views', $now['views'], $before['views'], 'number', 'eye', $views),
                $this->kpi('add_to_cart', $now['cart_adds'], $before['cart_adds'], 'number', 'cart-add'),
                $this->kpi('conversion_rate', ReportMath::percent($now['orders'], $now['views']), ReportMath::percent($before['orders'], $before['views']), 'percent', 'percent'),
                $this->kpi('units_sold', $now['units'], $before['units'], 'number', 'box'),
            ],
            charts: [
                $this->seriesChart('product_activity', 'line', $filters, [
                    ['label' => __('reporting::reports.series.views'), 'data' => $views, 'tone' => 'brand'],
                    ['label' => __('reporting::reports.series.cart_adds'), 'data' => ReportMath::series($filters, $this->pick($daily, 'cart_adds')), 'tone' => 'ink'],
                ]),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.products');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('product_name', __('reporting::reports.columns.product'), 'text', true, null, static fn (object $row): ?string => $row->tenant_name ?? null),
            new ReportColumn('views', __('reporting::reports.columns.views'), 'number'),
            new ReportColumn('cart_adds', __('reporting::reports.columns.cart_adds'), 'number'),
            new ReportColumn('orders', __('reporting::reports.columns.orders'), 'number'),
            new ReportColumn('units', __('reporting::reports.columns.units'), 'number'),
            new ReportColumn('revenue', __('reporting::reports.columns.revenue'), 'money'),
            new ReportColumn('conversion', __('reporting::reports.columns.conversion'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->orders, (int) $row->views)),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->products->byProduct($filters);
    }
}
