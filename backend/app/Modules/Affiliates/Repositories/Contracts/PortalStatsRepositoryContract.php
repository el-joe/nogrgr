<?php

namespace App\Modules\Affiliates\Repositories\Contracts;

use App\Modules\Affiliates\Models\AffiliateConversion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Read-only aggregates of one affiliate's own activity (affiliate panel + vendor
 * partner program page). Every amount is USD cents.
 */
interface PortalStatsRepositoryContract
{
    public function clicksBetween(int $affiliateId, Carbon $from, Carbon $to): int;

    public function clicksTotal(int $affiliateId): int;

    /**
     * Conversions created in the window (any status except rejected).
     */
    public function conversionsBetween(int $affiliateId, Carbon $from, Carbon $to): int;

    public function conversionsTotal(int $affiliateId): int;

    /**
     * Commission of pending conversions (not yet credited to the balance).
     */
    public function pendingCommission(int $affiliateId): int;

    /**
     * Sum of the affiliate's open (pending) payout requests.
     */
    public function pendingRequestsTotal(int $affiliateId): int;

    /**
     * @return array<string, int> Y-m-d => clicks
     */
    public function dailyClicks(int $affiliateId, Carbon $from, Carbon $to): array;

    /**
     * @return array<string, int> Y-m-d => conversions
     */
    public function dailyConversions(int $affiliateId, Carbon $from, Carbon $to): array;

    /**
     * @return Collection<int, AffiliateConversion>
     */
    public function recentConversions(int $affiliateId, int $limit = 5): Collection;
}
