<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\DTOs\DashboardPeriodData;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use App\Shared\Support\Locale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;

/**
 * Data for the admin dashboard (Figma 41:12632). Store counts are live; sales,
 * orders, refunds, requests, payouts and tickets read 0 until the Commerce,
 * AfterSales, Billing and Engagement modules record them (their metrics plug in here).
 * Platform health reads the real configuration of each service.
 */
class AdminDashboardService extends BaseService
{
    public function __construct(private readonly TenantRepositoryContract $tenants) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(DashboardPeriodData $period): array
    {
        $to = $period->periodTo !== null ? CarbonImmutable::parse($period->periodTo) : CarbonImmutable::today();
        $from = $period->periodFrom !== null ? CarbonImmutable::parse($period->periodFrom) : $to->subDays(29);
        $days = (int) $from->diffInDays($to) + 1;

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'activeStores' => $this->tenants->countByStatus(TenantStatus::Active),
            'kpis' => $this->kpis(),
            'revenue' => $this->revenueRanges($to),
            'ordersByStatus' => ['labels' => [], 'data' => []],
            'recentOrders' => [],
            'topVendors' => [],
            'attention' => $this->attention(),
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
     * @return list<array{key: string, value: string, raw: float|int, format: string, icon: string}>
     */
    private function kpis(): array
    {
        $zero = Money::zero();
        /* Figma shows "SAR 1,284,920": ISO code + Latin digits in both languages. */
        $money = static fn (Money $amount): string => $amount->currency.' '.Number::format($amount->toFloat(), 2, locale: Locale::numberLocale());

        return [
            ['key' => 'gross_sales', 'value' => $money($zero), 'raw' => 0, 'format' => 'currency:'.$zero->currency, 'icon' => 'wallet'],
            ['key' => 'orders', 'value' => '0', 'raw' => 0, 'format' => 'number', 'icon' => 'cart'],
            ['key' => 'platform_profit', 'value' => $money($zero), 'raw' => 0, 'format' => 'currency:'.$zero->currency, 'icon' => 'chart'],
            ['key' => 'refund_rate', 'value' => '0%', 'raw' => 0, 'format' => 'percent', 'icon' => 'return'],
        ];
    }

    /**
     * Gross sales vs platform share for the 7D / 30D / 12M switch (Figma 41:12632),
     * ending on the selected period's last day. Values read 0 until Commerce records orders.
     *
     * @return array<string, array{labels: list<string>, gross: list<int|float>, platform: list<int|float>}>
     */
    private function revenueRanges(CarbonImmutable $to): array
    {
        $locale = app()->getLocale();
        $series = static function (array $labels): array {
            $zeros = array_fill(0, count($labels), 0);

            return ['labels' => $labels, 'gross' => $zeros, 'platform' => $zeros];
        };

        $days = static fn (int $count): array => array_map(
            static fn (int $offset): string => $to->subDays($count - 1 - $offset)->locale($locale)->isoFormat('D MMM'),
            range(0, $count - 1),
        );
        $months = array_map(
            static fn (int $offset): string => $to->startOfMonth()->subMonths(11 - $offset)->locale($locale)->isoFormat('MMM'),
            range(0, 11),
        );

        return ['7d' => $series($days(7)), '30d' => $series($days(30)), '12m' => $series($months)];
    }

    /**
     * @return list<array{key: string, icon: string, count: int, tone: string, url: ?string}>
     */
    private function attention(): array
    {
        $url = static fn (string $route): ?string => Route::has($route) ? route($route) : null;

        return [
            ['key' => 'pending_vendors', 'icon' => 'store', 'count' => $this->tenants->countByStatus(TenantStatus::Pending), 'tone' => 'danger', 'url' => $url('admin.vendors.pending')],
            ['key' => 'open_requests', 'icon' => 'message', 'count' => 0, 'tone' => 'danger', 'url' => $url('admin.requests.products.index')],
            ['key' => 'returns_to_inspect', 'icon' => 'return', 'count' => 0, 'tone' => 'warning', 'url' => $url('admin.returns.index')],
            ['key' => 'kyc_to_review', 'icon' => 'shield', 'count' => 0, 'tone' => 'info', 'url' => $url('admin.vendors.kyc.index')],
            ['key' => 'failed_payouts', 'icon' => 'credit-card', 'count' => 0, 'tone' => 'danger', 'url' => $url('admin.finance.payouts.index')],
            ['key' => 'unanswered_tickets', 'icon' => 'lifebuoy', 'count' => 0, 'tone' => 'warning', 'url' => $url('admin.tickets.index')],
        ];
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
