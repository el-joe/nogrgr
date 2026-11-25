<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\Coupon;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central coupons (ADM-025) and their country / store targeting and redemptions.
 *
 * @extends RepositoryContract<Coupon>
 */
interface CouponRepositoryContract extends RepositoryContract
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<int>  $tenantIds
     */
    public function syncTargets(Coupon $coupon, array $countryIds, array $tenantIds): void;

    /**
     * Live (not deleted) central coupons a store may use: scope all, or the store is
     * selected. Countries eager loaded.
     *
     * @return Collection<int, Coupon>
     */
    public function applicableToTenant(int $tenantId, ?string $code = null): Collection;

    /**
     * Redemptions across all stores.
     */
    public function usageCount(int $couponId): int;

    /**
     * @param  list<int>  $couponIds
     * @return array<int, int> coupon id => redemptions across all stores
     */
    public function usageCounts(array $couponIds): array;

    public function customerUsageCount(int $couponId, int $tenantId, int $customerId): int;

    public function recordRedemption(int $couponId, int $tenantId, ?int $customerId, string $orderReference, int $discountMinor): void;

    /**
     * Countries targeted by at least one coupon (list tabs).
     *
     * @return array<int, string> id => name
     */
    public function targetedCountries(): array;

    public function codeExists(string $code, ?int $ignoreId = null): bool;
}
