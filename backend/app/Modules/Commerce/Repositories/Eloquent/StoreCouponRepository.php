<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Models\StoreCouponCountry;
use App\Modules\Commerce\Models\StoreCouponRedemption;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreCoupon>
 */
class StoreCouponRepository extends BaseRepository implements StoreCouponRepositoryContract
{
    protected function model(): string
    {
        return StoreCoupon::class;
    }

    public function listing(?string $code = null): Collection
    {
        return $this->query()
            ->with('countryRows')
            ->when($code !== null, fn (Builder $query) => $query->whereRaw('LOWER(code) = ?', [mb_strtolower((string) $code)]))
            ->orderBy('id')
            ->get();
    }

    public function syncCountries(StoreCoupon $coupon, array $countryIds): void
    {
        StoreCouponCountry::query()->where('store_coupon_id', $coupon->id)->delete();

        foreach (array_values(array_unique($countryIds)) as $countryId) {
            StoreCouponCountry::query()->insert(['store_coupon_id' => $coupon->id, 'country_id' => $countryId]);
        }

        $coupon->unsetRelation('countryRows');
    }

    public function usageCount(int $couponId): int
    {
        return StoreCouponRedemption::query()->where('store_coupon_id', $couponId)->count();
    }

    public function usageCounts(array $couponIds): array
    {
        if ($couponIds === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = StoreCouponRedemption::query()
            ->whereIn('store_coupon_id', $couponIds)
            ->selectRaw('store_coupon_id, COUNT(*) as aggregate')
            ->groupBy('store_coupon_id')
            ->pluck('aggregate', 'store_coupon_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        return $counts;
    }

    public function customerUsageCount(int $couponId, int $customerId): int
    {
        return StoreCouponRedemption::query()->where('store_coupon_id', $couponId)->where('customer_id', $customerId)->count();
    }

    public function recordRedemption(int $couponId, ?int $customerId, string $orderReference, int $discountMinor): void
    {
        StoreCouponRedemption::query()->firstOrCreate(
            ['store_coupon_id' => $couponId, 'order_reference' => $orderReference],
            ['customer_id' => $customerId, 'discount_minor' => $discountMinor, 'redeemed_at' => now()],
        );
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return StoreCoupon::withTrashed()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
