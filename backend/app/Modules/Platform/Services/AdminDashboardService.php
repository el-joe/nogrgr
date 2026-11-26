<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\DTOs\DashboardPeriodData;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\AfterSalesReportRepositoryContract;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Data for the admin dashboard (Figma 41:12632): KPIs with deltas vs the previous window,
 * the 7D / 30D / 12M revenue chart, orders by status, recent orders, top vendors of the
 * month and the needs-attention counters — all from central Reporting facts (order_facts,
 * refunds), never recomputed. Platform health reads the real configuration of each service.
 */
class AdminDashboardService extends BaseService
{
    public const RECENT_ORDERS = 5;

    public const TOP_VENDORS = 5;

    public function __construct(
        private readonly TenantRepositoryContract $tenants,
        private readonly ReportFactRepositoryContract $facts,
        private readonly AfterSalesReportRepositoryContract $afterSales,
        private readonly ComplianceProfileRepositoryContract $compliance,
        private readonly TenantPayoutRepositoryContract $payouts,
        private readonly CountryService $countries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(DashboardPeriodData $period, ?Authenticatable $admin = null): array
    {
        $filters = new ReportFilterData($period->periodFrom, $period->periodTo, null, $period->countryId);
        $to = CarbonImmutable::parse($filters->end()->toDateString());
        $from = CarbonImmutable::parse($filters->start()->toDateString());

        return [
            'from' => $from,
            'to' => $to,
            'days' => $filters->days(),
            'filters' => $filters,
            'activeStores' => $this->tenants->countByStatus(TenantStatus::Active),
            'kpis' => $this->kpis($filters),
            'revenue' => $this->revenueRanges($filters),
            'ordersByStatus' => $this->ordersByStatus($filters),
            'recentOrders' => $this->recentOrders($filters),
            'topVendors' => $this->topVendors($filters),
            'attention' => $this->attention($admin),
            'health' => $this->health(),
        ];
    }

    /**
     * Live platform facts shown on the admin sign-in panel.
     *
     * @return array{active_stores: int, pending_stores: int, languages: int}
     */
    public function signInStats(): array
    {
        return [
            'active_stores' => $this->tenants->countByStatus(TenantStatus::Active),
            'pending_stores' => $this->tenants->countByStatus(TenantStatus::Pending),
            'languages' => count(Locale::enabled()),
        ];
    }

    /**
     * @return list<array{key: string, value: string, raw: float|int, format: string, icon: string, delta: float|null, spark: list<int|float>}>
     */
    private function kpis(ReportFilterData $filters): array
    {
        $now = $this->facts->totals($filters);
        $before = $this->facts->totals($filters->previous());
        $refundRate = ReportMath::percent($this->afterSales->refundTotals($filters)['amount'], $now['gross']);
        $refundRateBefore = ReportMath::percent($this->afterSales->refundTotals($filters->previous())['amount'], $before['gross']);
        $daily = $this->facts->daily($filters);
        $series = static fn (string $key): array => ReportMath::series($filters, array_map(static fn (array $day): int => $day[$key], $daily));

        return [
            ['key' => 'gross_sales', 'value' => ReportFormat::money($now['gross']), 'raw' => $now['gross'] / 100, 'format' => 'money', 'icon' => 'wallet', 'delta' => ReportMath::delta($now['gross'], $before['gross']), 'spark' => $series('gross')],
            ['key' => 'orders', 'value' => ReportFormat::number($now['orders']), 'raw' => $now['orders'], 'format' => 'number', 'icon' => 'cart', 'delta' => ReportMath::delta($now['orders'], $before['orders']), 'spark' => $series('orders')],
            ['key' => 'platform_profit', 'value' => ReportFormat::money($now['platform']), 'raw' => $now['platform'] / 100, 'format' => 'money', 'icon' => 'chart', 'delta' => ReportMath::delta($now['platform'], $before['platform']), 'spark' => $series('platform')],
            ['key' => 'refund_rate', 'value' => ReportFormat::percent($refundRate), 'raw' => $refundRate, 'format' => 'percent', 'icon' => 'return', 'delta' => ReportMath::delta($refundRate, $refundRateBefore), 'spark' => []],
        ];
    }

    /**
     * Gross sales vs platform share (USD) for the 7D / 30D / 12M switch (Figma 41:12632),
     * ending on the selected period's last day.
     *
     * @return array<string, array{labels: list<string>, gross: list<float>, platform: list<float>}>
     */
    private function revenueRanges(ReportFilterData $filters): array
    {
        $end = $filters->end();
        $year = new ReportFilterData($end->startOfMonth()->subMonths(11)->toDateString(), $end->toDateString(), null, $filters->countryId);
        $daily = $this->facts->daily($year);
        $range = function (ReportFilterData $window) use ($daily): array {
            $pick = static fn (string $key): array => array_map(static fn (int $minor): float => round($minor / 100, 2), ReportMath::series($window, array_map(static fn (array $day): int => $day[$key], $daily)));

            return ['labels' => ReportFormat::bucketLabels($window), 'gross' => $pick('gross'), 'platform' => $pick('platform')];
        };

        return [
            '7d' => $range(new ReportFilterData($end->subDays(6)->toDateString(), $end->toDateString())),
            '30d' => $range(new ReportFilterData($end->subDays(29)->toDateString(), $end->toDateString())),
            '12m' => $range($year),
        ];
    }

    /**
     * @return array{labels: list<string>, data: list<int>, tones: list<string>, total: int}
     */
    private function ordersByStatus(ReportFilterData $filters): array
    {
        $tones = ['delivered' => 'success', 'completed' => 'success', 'shipped' => 'info', 'processing' => 'warning', 'paid' => 'brand', 'cancelled' => 'danger', 'refunded' => 'ink', 'returned' => 'muted'];
        $counts = $this->facts->statusCounts($filters);
        arsort($counts);
        $result = ['labels' => [], 'data' => [], 'tones' => [], 'total' => array_sum($counts)];

        foreach ($counts as $status => $count) {
            $result['labels'][] = OrderStatus::tryFrom($status)?->label() ?? $status;
            $result['data'][] = $count;
            $result['tones'][] = $tones[$status] ?? 'muted';
        }

        return $result;
    }

    /**
     * @return list<array{number: string, ago: string, store: string, slug: string, country: string|null, total: string, status: string, status_label: string}>
     */
    private function recentOrders(ReportFilterData $filters): array
    {
        $countries = $this->countries->options();

        return $this->facts->latest($filters, self::RECENT_ORDERS)->map(static fn (OrderFact $fact): array => [
            'number' => $fact->order_number,
            'ago' => (string) $fact->placed_at?->locale(app()->getLocale())->diffForHumans(),
            'store' => (string) $fact->tenant?->getAttribute('name'),
            'slug' => (string) $fact->tenant?->getAttribute('slug'),
            'country' => $fact->country_id !== null ? ($countries[$fact->country_id] ?? null) : null,
            'total' => ReportFormat::money($fact->grand_total),
            'status' => $fact->status,
            'status_label' => OrderStatus::tryFrom($fact->status)?->label() ?? $fact->status,
        ])->values()->all();
    }

    /**
     * Top stores by gross sales this calendar month (Figma "Top vendors this month").
     *
     * @return list<array{name: string, slug: string, gross: string, orders: int, percent: float}>
     */
    private function topVendors(ReportFilterData $filters): array
    {
        $end = $filters->end();
        $month = new ReportFilterData($end->startOfMonth()->toDateString(), $end->toDateString(), null, $filters->countryId);
        $rows = $this->facts->byTenant($month)->orderByDesc('gross')->limit(self::TOP_VENDORS)->get();
        $top = (int) ($rows->first()->gross ?? 0);

        return $rows->map(static fn (object $row): array => [
            'name' => (string) $row->tenant_name,
            'slug' => (string) $row->tenant_slug,
            'gross' => ReportFormat::money((int) $row->gross),
            'orders' => (int) $row->orders,
            'percent' => ReportMath::percent((int) $row->gross, $top),
        ])->values()->all();
    }

    /**
     * @return list<array{key: string, icon: string, count: int, tone: string, url: ?string}>
     */
    private function attention(?Authenticatable $admin): array
    {
        $url = static fn (string $route): ?string => Route::has($route) ? route($route) : null;
        $badges = $this->badges($admin);
        $kyc = $this->compliance->countsByStatus();

        return [
            ['key' => 'pending_vendors', 'icon' => 'store', 'count' => $this->tenants->countByStatus(TenantStatus::Pending), 'tone' => 'danger', 'url' => $url('admin.vendors.pending')],
            ['key' => 'open_requests', 'icon' => 'message', 'count' => (int) ($badges['requests'] ?? 0), 'tone' => 'danger', 'url' => $url('admin.requests.products.index')],
            ['key' => 'returns_to_inspect', 'icon' => 'return', 'count' => $this->afterSales->awaitingInspection(), 'tone' => 'warning', 'url' => $url('admin.returns.index')],
            ['key' => 'kyc_to_review', 'icon' => 'shield', 'count' => (int) ($kyc[ComplianceStatus::NeedsReview->value] ?? 0), 'tone' => 'info', 'url' => $url('admin.vendors.compliance.index')],
            ['key' => 'failed_payouts', 'icon' => 'credit-card', 'count' => $this->payouts->stats()['cancelled'], 'tone' => 'danger', 'url' => $url('admin.finance.payouts.index')],
            ['key' => 'unanswered_tickets', 'icon' => 'lifebuoy', 'count' => (int) ($badges['tickets'] ?? 0), 'tone' => 'warning', 'url' => $url('admin.tickets.index')],
        ];
    }

    /**
     * Sidebar badge counts of the admin surface (same numbers as the menu).
     *
     * @return array<string, int>
     */
    private function badges(?Authenticatable $admin): array
    {
        $counts = [];

        foreach (app()->tagged(PanelBadgeCounter::TAG) as $counter) {
            if ($counter instanceof PanelBadgeCounter && $counter->surface() === 'admin') {
                $counts = [...$counts, ...$counter->counts($admin)];
            }
        }

        return $counts;
    }

    /**
     * @return list<array{key: string, status: string, detail: string}>
     */
    private function health(): array
    {
        $queue = (string) config('queue.default');
        $broadcast = (string) config('broadcasting.default', 'null');
        $mailer = (string) config('mail.default');
        $openAi = filled(config('openai.api_key'));

        return [
            ['key' => 'queue', 'status' => in_array($queue, ['sync', 'null'], true) ? 'degraded' : 'configured', 'detail' => $queue],
            ['key' => 'realtime', 'status' => in_array($broadcast, ['null', 'log', ''], true) ? 'not_configured' : 'configured', 'detail' => $broadcast !== '' ? $broadcast : 'null'],
            ['key' => 'mail', 'status' => in_array($mailer, ['log', 'array'], true) ? 'test' : 'configured', 'detail' => $mailer],
            ['key' => 'currency', 'status' => 'not_configured', 'detail' => 'open.er-api.com'],
            ['key' => 'openai', 'status' => $openAi ? 'configured' : 'not_configured', 'detail' => $openAi ? 'api' : '—'],
        ];
    }
}
