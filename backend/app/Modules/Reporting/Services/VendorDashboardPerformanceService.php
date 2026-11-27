<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CountryRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\VendorPerformanceData;
use App\Modules\Reporting\Enums\DashboardPeriod;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\VendorAnalyticsRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Carbon\CarbonImmutable;

/**
 * Vendor dashboard "Your store's performance" (VEN-009, client frame 12:11267): from the
 * central Reporting facts of one store, for the chosen period vs the window right before:
 * Orders (booked), Average order (gross / orders), Outstanding (booked, not paid — COD),
 * Customers (distinct buyers); "Gross vs Collected" per bucket; "Status Mix" of placed
 * orders (not awaiting payment) with share; the 5 latest orders. Same definitions as the
 * Orders analytics tab (VEN-045a) so both pages always agree. All amounts are USD; when a
 * selling country is picked (top bar), money values also get a display conversion into
 * that country's currency — only if its rate was synced at least once (QA-FINAL-009).
 */
class VendorDashboardPerformanceService extends BaseService
{
    public const LATEST = 5;

    /** Status => chart tone (Figma Status Mix: Paid green, Pending amber, Processing blue, Cancelled red). */
    private const TONES = [
        'paid' => 'success',
        'completed' => 'success',
        'delivered' => 'success',
        'processing' => 'info',
        'shipped' => 'brand',
        'cancelled' => 'danger',
        'refunded' => 'muted',
        'returned' => 'muted',
    ];

    public function __construct(
        private readonly VendorAnalyticsRepositoryContract $analytics,
        private readonly ReportFactRepositoryContract $facts,
        private readonly CountryRepositoryContract $countries,
        private readonly CurrencyRepositoryContract $currencies,
    ) {}

    /**
     * @param  callable(OrderFact): ?string|null  $orderUrl  link of a latest order (null = no link)
     */
    public function summary(int $tenantId, DashboardPeriod $period, ?int $countryId = null, ?callable $orderUrl = null, ?CarbonImmutable $today = null): VendorPerformanceData
    {
        $filters = $period->filters($tenantId, $today);
        $previous = $filters->previous();
        $currency = $this->displayCurrency($countryId);

        $now = $this->analytics->orderTotals($filters);
        $before = $this->analytics->orderTotals($previous);
        $customers = $this->facts->customerTotals($filters)['customers'];
        $customersBefore = $this->facts->customerTotals($previous)['customers'];
        $average = ReportMath::average($now['gross'], $now['orders']);
        $averageBefore = ReportMath::average($before['gross'], $before['orders']);

        $kpis = [
            $this->kpi('orders', $now['orders'], $before['orders'], false, 'cart', null),
            $this->kpi('average', $average, $averageBefore, true, 'wallet', $currency),
            $this->kpi('outstanding', $now['outstanding'], $before['outstanding'], true, 'clock', $currency),
            $this->kpi('customers', $customers, $customersBefore, false, 'users', null),
        ];

        $gross = ReportMath::series($filters, array_map(static fn (array $day): int => (int) ($day['gross'] ?? 0), $this->facts->daily($filters)));
        $collected = ReportMath::series($filters, $this->analytics->collectedDaily($filters));

        [$statusMix, $placed] = $this->statusMix($filters);

        return new VendorPerformanceData(
            period: $period,
            periodLabel: ReportFormat::period($filters),
            kpis: $kpis,
            grossChart: [
                'labels' => ReportFormat::bucketLabels($filters),
                'series' => [
                    ['label' => __('reporting::vendor_dashboard.chart.gross'), 'data' => self::dollars($gross), 'tone' => 'info'],
                    ['label' => __('reporting::vendor_dashboard.chart.collected'), 'data' => self::dollars($collected), 'tone' => 'success'],
                ],
            ],
            statusMix: $statusMix,
            placedTotal: $placed,
            latestOrders: $this->latest($tenantId, $currency, $orderUrl),
            displayCurrency: $currency?->code,
        );
    }

    /**
     * The currency a money value is also shown in: the selected selling country's currency,
     * only when it is active, not USD and its rate was synced (never a stale default rate).
     */
    public function displayCurrency(?int $countryId): ?Currency
    {
        if ($countryId === null) {
            return null;
        }

        $code = $this->countries->findActive($countryId)?->currency_code;
        $currency = is_string($code) && $code !== '' ? $this->currencies->findByCode($code) : null;

        if (! $currency instanceof Currency || ! $currency->is_active || $currency->isBase() || $currency->rate_synced_at === null || (float) $currency->rate <= 0) {
            return null;
        }

        return $currency;
    }

    /**
     * @return array{key: string, label: string, value: string, raw: int, delta: float|null, note: string, icon: string, converted: string|null}
     */
    private function kpi(string $key, int $current, int $previous, bool $money, string $icon, ?Currency $currency): array
    {
        return [
            'key' => $key,
            'label' => __('reporting::vendor_dashboard.kpi.'.$key),
            'value' => $money ? ReportFormat::money($current) : ReportFormat::number($current),
            'raw' => $current,
            'delta' => ReportMath::delta($current, $previous),
            'note' => __('reporting::vendor_dashboard.kpi.'.$key.'_note'),
            'icon' => $icon,
            'converted' => $money ? $this->convert($current, $currency) : null,
        ];
    }

    /**
     * @return array{0: list<array{status: string, label: string, count: int, percent: float, percent_label: string, tone: string}>, 1: int}
     */
    private function statusMix(ReportFilterData $filters): array
    {
        $counts = array_filter($this->facts->statusCounts($filters), static fn (int $count): bool => $count > 0);
        arsort($counts);
        $placed = array_sum($counts);
        $rows = [];

        foreach ($counts as $status => $count) {
            $percent = ReportMath::percent($count, $placed);
            $rows[] = [
                'status' => (string) $status,
                'label' => OrderStatus::tryFrom((string) $status)?->label() ?? (string) $status,
                'count' => $count,
                'percent' => $percent,
                'percent_label' => ReportFormat::percent($percent),
                'tone' => self::TONES[$status] ?? 'warning',
            ];
        }

        return [$rows, $placed];
    }

    /**
     * @param  callable(OrderFact): ?string|null  $orderUrl
     * @return list<array{number: string, customer: string, placed_at: string, status: string, total: string, converted: string|null, url: string|null}>
     */
    private function latest(int $tenantId, ?Currency $currency, ?callable $orderUrl): array
    {
        $rows = [];

        foreach ($this->facts->latest(new ReportFilterData(tenantId: $tenantId), self::LATEST) as $fact) {
            /** @var OrderFact $fact */
            $rows[] = [
                'number' => (string) $fact->order_number,
                'customer' => (string) ($fact->customer_name ?: __('reporting::vendor_dashboard.latest.guest')),
                'placed_at' => $fact->placed_at !== null ? ReportFormat::day($fact->placed_at->toDateString()) : '—',
                'status' => (string) $fact->status,
                'total' => ReportFormat::money((int) $fact->grand_total),
                'converted' => $this->convert((int) $fact->grand_total, $currency),
                'url' => $orderUrl !== null ? $orderUrl($fact) : null,
            ];
        }

        return $rows;
    }

    private function convert(int $minor, ?Currency $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        return Money::of($currency->convertFromBase(Money::ofMinor($minor)), $currency->code)->format();
    }

    /**
     * @param  list<int|float>  $minor
     * @return list<float>
     */
    private static function dollars(array $minor): array
    {
        return array_map(static fn ($value): float => round($value / 100, 2), $minor);
    }
}
