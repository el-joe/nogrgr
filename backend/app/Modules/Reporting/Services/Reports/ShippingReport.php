<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070e Shipping (Figma 75:69019): orders shipped in the window by carrier and
 * destination country (the shipping zone unit), delivery performance (on time =
 * delivered no later than the promised `delivery_max_date`, average shipped → delivered
 * days) and the fulfilment status mix.
 */
final class ShippingReport extends BaseReport
{
    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->facts->shippingTotals($filters);
        $before = $this->facts->shippingTotals($filters->previous());
        $statuses = [];

        foreach ($this->facts->fulfilmentCounts($filters) as $status => $count) {
            $statuses[FulfilmentStatus::tryFrom($status)?->label() ?? $status] = $count;
        }

        $carriers = [];

        foreach ($this->facts->shipments($filters)->get() as $row) {
            $name = $row->company !== '' ? (string) $row->company : __('reporting::reports.no_carrier');
            $carriers[$name] = ($carriers[$name] ?? 0) + (int) $row->shipments;
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('shipments', $now['shipped'], $before['shipped'], 'number', 'truck'),
                $this->kpi('delivered', $now['delivered'], $before['delivered'], 'number', 'check-circle'),
                $this->kpi('on_time_rate', ReportMath::percent($now['on_time'], $now['delivered']), ReportMath::percent($before['on_time'], $before['delivered']), 'percent', 'clock'),
                $this->kpi('avg_delivery_days', $now['avg_days'], $before['avg_days'], 'decimal', 'calendar'),
            ],
            charts: [
                [...$this->donut('shipments_by_carrier', $carriers, ReportFormat::number($now['shipped'])), 'wide' => false],
                $this->donut('fulfilment_status', $statuses, ReportFormat::number(array_sum($statuses))),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.shipping');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('company', __('reporting::reports.columns.carrier'), 'text', true, static fn (object $row): string => $row->company !== '' ? (string) $row->company : __('reporting::reports.no_carrier')),
            new ReportColumn('country_id', __('reporting::reports.columns.country'), 'text', true, fn (object $row): string => $this->countryName((int) $row->country_id)),
            new ReportColumn('shipments', __('reporting::reports.columns.shipments'), 'number'),
            new ReportColumn('delivered', __('reporting::reports.columns.delivered'), 'number'),
            new ReportColumn('on_time', __('reporting::reports.columns.on_time'), 'percent', false, static fn (object $row): float => ReportMath::percent((int) $row->on_time, (int) $row->delivered)),
            new ReportColumn('avg_days', __('reporting::reports.columns.avg_days'), 'decimal', true, static fn (object $row): float => round((float) $row->avg_days, 1)),
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
