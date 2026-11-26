<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\PortalStatsRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<AffiliateConversion>
 */
class PortalStatsRepository extends BaseRepository implements PortalStatsRepositoryContract
{
    protected function model(): string
    {
        return AffiliateConversion::class;
    }

    public function clicksBetween(int $affiliateId, Carbon $from, Carbon $to): int
    {
        return AffiliateClick::query()->where('affiliate_id', $affiliateId)->whereBetween('created_at', [$from, $to])->count();
    }

    public function clicksTotal(int $affiliateId): int
    {
        return AffiliateClick::query()->where('affiliate_id', $affiliateId)->count();
    }

    public function conversionsBetween(int $affiliateId, Carbon $from, Carbon $to): int
    {
        return $this->conversions($affiliateId)->whereBetween('created_at', [$from, $to])->count();
    }

    public function conversionsTotal(int $affiliateId): int
    {
        return $this->conversions($affiliateId)->count();
    }

    public function pendingCommission(int $affiliateId): int
    {
        return (int) AffiliateConversion::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', ConversionStatus::Pending->value)
            ->sum('commission_amount');
    }

    public function pendingRequestsTotal(int $affiliateId): int
    {
        return (int) AffiliatePayoutRequest::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', PayoutRequestStatus::Pending->value)
            ->sum('amount');
    }

    public function dailyClicks(int $affiliateId, Carbon $from, Carbon $to): array
    {
        return $this->daily(AffiliateClick::query()->where('affiliate_id', $affiliateId), $from, $to);
    }

    public function dailyConversions(int $affiliateId, Carbon $from, Carbon $to): array
    {
        return $this->daily($this->conversions($affiliateId), $from, $to);
    }

    public function recentConversions(int $affiliateId, int $limit = 5): Collection
    {
        return AffiliateConversion::query()
            ->with(['tenant', 'promoCode'])
            ->where('affiliate_id', $affiliateId)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Builder<AffiliateConversion>
     */
    private function conversions(int $affiliateId): Builder
    {
        return AffiliateConversion::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', '!=', ConversionStatus::Rejected->value);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    private function daily(Builder $query, Carbon $from, Carbon $to): array
    {
        return $query
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }
}
