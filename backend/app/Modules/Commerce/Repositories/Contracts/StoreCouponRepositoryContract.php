<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\StoreCoupon;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store-owned coupons (tenant DB) and their redemptions.
 *
 * @extends RepositoryContract<StoreCoupon>
 */
interface StoreCouponRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, StoreCoupon> countries eager loaded, by id
     */
    public function listing(?string $code = null): Collection;

    /**
     * @param  list<int>  $countryIds
     */
    public function syncCountries(StoreCoupon $coupon, array $countryIds): void;

    public function usageCount(int $couponId): int;

    /**
     * @param  list<int>  $couponIds
     * @return array<int, int>
     */
    public function usageCounts(array $couponIds): array;

    public function customerUsageCount(int $couponId, int $customerId): int;

    public function recordRedemption(int $couponId, ?int $customerId, string $orderReference, int $discountMinor): void;

    public function codeExists(string $code, ?int $ignoreId = null): bool;
}
