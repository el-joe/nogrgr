<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\CouponRedemption;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Localization\Models\Country;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Coupon>
 */
class CouponRepository extends BaseRepository implements CouponRepositoryContract
{
    protected function model(): string
    {
        return Coupon::class;
    }

    public function syncTargets(Coupon $coupon, array $countryIds, array $tenantIds): void
    {
        $coupon->countries()->sync($countryIds);
        $coupon->tenants()->sync($coupon->store_scope === PromotionScope::SelectedStores ? $tenantIds : []);
    }

    public function applicableToTenant(int $tenantId, ?string $code = null): Collection
    {
        return $this->query()
            ->with('countries:id')
            ->when($code !== null, fn (Builder $query) => $query->whereRaw('LOWER(code) = ?', [mb_strtolower((string) $code)]))
            ->where(fn (Builder $query) => $query
                ->where('store_scope', PromotionScope::AllStores->value)
                ->orWhereHas('tenants', fn (Builder $tenants) => $tenants->whereKey($tenantId)))
            ->orderBy('id')
            ->get();
    }

    public function usageCount(int $couponId): int
    {
        return CouponRedemption::withoutTenantScope()->where('coupon_id', $couponId)->count();
    }

    public function usageCounts(array $couponIds): array
    {
        if ($couponIds === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = CouponRedemption::withoutTenantScope()
            ->whereIn('coupon_id', $couponIds)
            ->selectRaw('coupon_id, COUNT(*) as aggregate')
            ->groupBy('coupon_id')
            ->pluck('aggregate', 'coupon_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        return $counts;
    }

    public function customerUsageCount(int $couponId, int $tenantId, int $customerId): int
    {
        return CouponRedemption::query()->forTenant($tenantId)->where('coupon_id', $couponId)->where('customer_id', $customerId)->count();
    }

    public function recordRedemption(int $couponId, int $tenantId, ?int $customerId, string $orderReference, int $discountMinor): void
    {
        CouponRedemption::withoutTenantScope()->firstOrCreate(
            ['coupon_id' => $couponId, 'tenant_id' => $tenantId, 'order_reference' => $orderReference],
            ['customer_id' => $customerId, 'discount_minor' => $discountMinor, 'redeemed_at' => now()],
        );
    }

    public function targetedCountries(): array
    {
        $options = [];

        $countries = Country::query()
            ->whereIn('id', fn ($query) => $query->select('country_id')->from('coupon_country')
                ->whereIn('coupon_id', fn ($coupons) => $coupons->select('id')->from('coupons')->whereNull('deleted_at')))
            ->get(['id', 'name', 'flag']);

        foreach ($countries as $country) {
            $options[(int) $country->id] = $country->name;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return Coupon::withTrashed()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
