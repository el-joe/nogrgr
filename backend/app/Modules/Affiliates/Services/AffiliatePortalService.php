<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\PortalStatsRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * What the affiliate dashboard (AFF-009), links page (AFF-010) and the vendor partner
 * program page (VEN-055 / 009b) show: KPIs with the previous-period delta, the
 * clicks / conversions chart, the referral link and program terms. USD only.
 */
class AffiliatePortalService extends BaseService
{
    public function __construct(
        private readonly PortalStatsRepositoryContract $stats,
        private readonly PayoutRequestService $payoutRequests,
        private readonly AffiliateAccountService $accounts,
        private readonly ProgramSettingsService $settings,
    ) {}

    /**
     * @return array{clicks: int, clicksDelta: ?float, conversions: int, conversionsDelta: ?float, clicksTotal: int, conversionsTotal: int, pendingCommission: Money, balance: Money, earned: Money, paid: Money, requestable: Money, pendingRequests: Money, minimum: Money, canRequest: bool, commission: string, cookieDays: int, link: string, spark: array{clicks: list<int>, conversions: list<int>}}
     */
    public function overview(Affiliate $affiliate, int $days = 30): array
    {
        $to = now();
        $from = $to->copy()->subDays($days)->startOfDay();
        $prevFrom = $from->copy()->subDays($days);
        $clicks = $this->stats->clicksBetween($affiliate->id, $from, $to);
        $previousClicks = $this->stats->clicksBetween($affiliate->id, $prevFrom, $from);
        $conversions = $this->stats->conversionsBetween($affiliate->id, $from, $to);
        $previousConversions = $this->stats->conversionsBetween($affiliate->id, $prevFrom, $from);
        $chart = $this->chart($affiliate, 14);

        return [
            'clicks' => $clicks,
            'clicksDelta' => $this->delta($clicks, $previousClicks),
            'conversions' => $conversions,
            'conversionsDelta' => $this->delta($conversions, $previousConversions),
            'clicksTotal' => $this->stats->clicksTotal($affiliate->id),
            'conversionsTotal' => $this->stats->conversionsTotal($affiliate->id),
            'pendingCommission' => Money::ofMinor($this->stats->pendingCommission($affiliate->id)),
            'balance' => Money::ofMinor((int) $affiliate->balance),
            'earned' => Money::ofMinor((int) $affiliate->total_earned),
            'paid' => Money::ofMinor((int) $affiliate->total_paid),
            'requestable' => $this->payoutRequests->requestable($affiliate),
            'pendingRequests' => Money::ofMinor($this->stats->pendingRequestsTotal($affiliate->id)),
            'minimum' => $this->payoutRequests->minimum(),
            'canRequest' => $this->payoutRequests->canRequest($affiliate),
            'commission' => $this->accounts->commissionLabel($affiliate),
            'cookieDays' => $this->settings->cookieDays(),
            'link' => $this->referralLink($affiliate),
            'spark' => ['clicks' => $chart['clicks'], 'conversions' => $chart['conversions']],
        ];
    }

    /**
     * Chart ranges for <x-ui::chart-card> (7D / 30D per day, 12M per month): clicks and conversions.
     *
     * @return array<string, array{labels: list<string>, series: list<array{label: string, data: list<int>, tone: string}>}>
     */
    public function chartRanges(Affiliate $affiliate): array
    {
        $ranges = [];

        foreach (['7d' => 7, '30d' => 30, '12m' => null] as $key => $days) {
            $chart = $days === null ? $this->monthly($affiliate) : $this->chart($affiliate, $days);
            $ranges[$key] = [
                'labels' => $chart['labels'],
                'series' => [
                    ['label' => (string) __('affiliates::panel.dashboard.chart_clicks'), 'data' => $chart['clicks'], 'tone' => 'brand'],
                    ['label' => (string) __('affiliates::panel.dashboard.chart_conversions'), 'data' => $chart['conversions'], 'tone' => 'ink'],
                ],
            ];
        }

        return $ranges;
    }

    /**
     * @return Collection<int, AffiliateConversion>
     */
    public function recentConversions(Affiliate $affiliate, int $limit = 5): Collection
    {
        return $this->stats->recentConversions($affiliate->id, $limit);
    }

    /**
     * The newest active promo code of the affiliate (shown on the referral card).
     */
    public function activePromoCode(Affiliate $affiliate): ?AffiliatePromoCode
    {
        /** @var AffiliatePromoCode|null $code */
        $code = $affiliate->promoCodes()->where('is_active', true)->latest('id')->first();

        return $code;
    }

    public function referralLink(Affiliate $affiliate): string
    {
        $home = Route::has('central.home') ? route('central.home') : url('/');

        return rtrim($home, '/').'/?ref='.rawurlencode((string) $affiliate->code);
    }

    /**
     * @return array{labels: list<string>, clicks: list<int>, conversions: list<int>}
     */
    private function chart(Affiliate $affiliate, int $days): array
    {
        $to = now();
        $from = $to->copy()->subDays($days - 1)->startOfDay();
        $clicks = $this->stats->dailyClicks($affiliate->id, $from, $to);
        $conversions = $this->stats->dailyConversions($affiliate->id, $from, $to);
        $result = ['labels' => [], 'clicks' => [], 'conversions' => []];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $result['labels'][] = $this->label($day);
            $result['clicks'][] = $clicks[$key] ?? 0;
            $result['conversions'][] = $conversions[$key] ?? 0;
        }

        return $result;
    }

    /**
     * @return array{labels: list<string>, clicks: list<int>, conversions: list<int>}
     */
    private function monthly(Affiliate $affiliate): array
    {
        $to = now();
        $from = $to->copy()->subMonths(11)->startOfMonth();
        $sum = static function (array $daily): array {
            $months = [];

            foreach ($daily as $day => $total) {
                $month = substr((string) $day, 0, 7);
                $months[$month] = ($months[$month] ?? 0) + $total;
            }

            return $months;
        };
        $clicks = $sum($this->stats->dailyClicks($affiliate->id, $from, $to));
        $conversions = $sum($this->stats->dailyConversions($affiliate->id, $from, $to));
        $result = ['labels' => [], 'clicks' => [], 'conversions' => []];

        for ($month = $from->copy(); $month->lte($to); $month->addMonth()) {
            $key = $month->format('Y-m');
            $result['labels'][] = $month->copy()->locale(app()->getLocale())->translatedFormat('M Y');
            $result['clicks'][] = $clicks[$key] ?? 0;
            $result['conversions'][] = $conversions[$key] ?? 0;
        }

        return $result;
    }

    private function label(Carbon $day): string
    {
        return $day->copy()->locale(app()->getLocale())->translatedFormat('j M');
    }

    private function delta(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
