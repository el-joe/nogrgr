<?php

namespace App\Modules\Reporting\DTOs;

use App\Modules\Reporting\Enums\DashboardPeriod;
use App\Shared\DTOs\BaseDTO;

/**
 * "Your store's performance" block of the vendor dashboard (VEN-009, client frame
 * 12:11267): KPI cards, gross vs collected chart, status mix and the latest orders.
 * Amounts are USD (stored base); `converted` holds the display conversion into the
 * selected country's currency when its rate was synced (display only).
 */
final class VendorPerformanceData extends BaseDTO
{
    /**
     * @param  list<array{key: string, label: string, value: string, raw: int, delta: float|null, note: string, icon: string, converted: string|null}>  $kpis
     * @param  array{labels: list<string>, series: list<array{label: string, data: list<float>, tone: string}>}  $grossChart
     * @param  list<array{status: string, label: string, count: int, percent: float, percent_label: string, tone: string}>  $statusMix
     * @param  list<array{number: string, customer: string, placed_at: string, status: string, total: string, converted: string|null, url: string|null}>  $latestOrders
     */
    public function __construct(
        public readonly DashboardPeriod $period,
        public readonly string $periodLabel,
        public readonly array $kpis,
        public readonly array $grossChart,
        public readonly array $statusMix,
        public readonly int $placedTotal,
        public readonly array $latestOrders,
        public readonly ?string $displayCurrency,
    ) {}

    public function hasOrders(): bool
    {
        return $this->placedTotal > 0 || $this->latestOrders !== [];
    }
}
