<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use App\Shared\Services\BaseService;
use Carbon\CarbonImmutable;

/**
 * Vendor profile › Stats & orders (ADM-041e, Figma 72:51480): the store's last 12 months
 * from order facts — orders, customers, AOV, gross sales, orders per month and status mix.
 */
class VendorStatsService extends BaseService
{
    public const MONTHS = 12;

    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    /**
     * @return array{tiles: list<array{label: string, value: string}>, chart: array{labels: list<string>, data: list<int>}, statuses: array<string, string>, period: string}
     */
    public function overview(int $tenantId): array
    {
        $end = CarbonImmutable::today();
        $filters = new ReportFilterData($end->startOfMonth()->subMonths(self::MONTHS - 1)->toDateString(), $end->toDateString(), $tenantId);
        $totals = $this->facts->totals($filters);
        $customers = $this->facts->customerTotals($filters)['customers'];
        $daily = array_map(static fn (array $day): int => $day['orders'], $this->facts->daily($filters));

        return [
            'tiles' => [
                ['label' => __('reporting::vendor_stats.orders'), 'value' => ReportFormat::number($totals['orders'])],
                ['label' => __('reporting::vendor_stats.customers'), 'value' => ReportFormat::number($customers)],
                ['label' => __('reporting::vendor_stats.aov'), 'value' => ReportFormat::money(ReportMath::average($totals['gross'], $totals['orders']))],
                ['label' => __('reporting::vendor_stats.gross'), 'value' => ReportFormat::money($totals['gross'])],
            ],
            'chart' => ['labels' => ReportFormat::bucketLabels($filters), 'data' => ReportMath::series($filters, $daily)],
            'statuses' => array_diff_key(OrderStatus::options(), [OrderStatus::PendingPayment->value => true]),
            'period' => ReportFormat::period($filters),
        ];
    }
}
