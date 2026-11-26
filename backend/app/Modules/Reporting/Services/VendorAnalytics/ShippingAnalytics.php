<?php

namespace App\Modules\Reporting\Services\VendorAnalytics;

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * VEN-045b Shipping analytics (old tenant.analytics.shipping.data.monthly, Figma 87:108835):
 * orders shipped in the window — on-time rate (delivered no later than the promised date ÷
 * delivered), average shipped → delivered days, average shipping charge per order, delayed
 * orders; deliveries per carrier, fulfilment mix; carrier × country table.
 */
final class ShippingAnalytics extends VendorReport
{
    public function __construct(
        private readonly VendorAnalyticsRepositoryContract $analytics,
        private readonly ReportFactRepositoryContract $facts,
    ) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->shippingTotals($filters);
        $before = $this->facts->shippingTotals($filters->previous());
        $extra = $this->analytics->shippingExtras($filters);
        $extraBefore = $this->analytics->shippingExtras($filters->previous());
        $carriers = [];

        foreach ($this->facts->shipments($filters)->get() as $row) {
            $name = $row->company !== '' ? (string) $row->company : __('reporting::vendor_analytics.no_carrier');
            $carriers[$name] = ($carriers[$name] ?? 0) + (int) $row->shipments;
        }

        arsort($carriers);
        $statuses = [];

        foreach ($this->facts->fulfilmentCounts($filters) as $status => $count) {
            $statuses[FulfilmentStatus::tryFrom($status)?->label() ?? $status] = $count;
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('on_time_rate', ReportMath::percent($now['on_time'], $now['delivered']), ReportMath::percent($before['on_time'], $before['delivered']), 'percent', 'truck'),
                $this->kpi('avg_delivery_days', $now['avg_days'], $before['avg_days'], 'decimal', 'clock'),
                $this->kpi('shipping_per_order', $extra['avg_shipping'], $extraBefore['avg_shipping'], 'money', 'wallet'),
                $this->kpi('delayed', $extra['delayed'], $extraBefore['delayed'], 'number', 'alert-triangle'),
            ],
            charts: [
                [...$this->categoryChart('deliveries_by_company', array_slice($carriers, 0, 8, true), __('reporting::vendor_analytics.series.shipments')), 'wide' => true],
                $this->donut('fulfilment_mix', $statuses, ReportFormat::number($now['shipped'])),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::vendor_analytics.tables.shipping');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('company', __('reporting::vendor_analytics.columns.carrier'), 'text', true, static fn (object $row): string => $row->company !== '' ? (string) $row->company : __('reporting::vendor_analytics.no_carrier')),
            new ReportColumn('country_id', __('reporting::vendor_analytics.columns.country'), 'text', true, fn (object $row): string => $this->countryName((int) $row->country_id)),
            new ReportColumn('shipments', __('reporting::vendor_analytics.columns.shipments'), 'number'),
            new ReportColumn('delivered', __('reporting::vendor_analytics.columns.delivered'), 'number'),
            new ReportColumn('on_time', __('reporting::vendor_analytics.columns.on_time'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->on_time, (int) $row->delivered)),
            new ReportColumn('avg_days', __('reporting::vendor_analytics.columns.avg_days'), 'decimal', true, static fn (object $row): float => round((float) $row->avg_days, 1)),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->facts->shipments($filters);
    }

    public function defaultOrder(): array
    {
        return [2, 'desc'];
    }
}
