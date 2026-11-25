<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\DTOs\Promotions\PromotionCoupon;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\HiddenPromotionRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The ONE coupon reader of a store (decision #65 b): the store's own coupons (tenant DB)
 * merged with the central coupons that target it (central DB), each tagged with its
 * `source`. Nothing is copied between databases.
 *
 *  - central coupons: not deleted; scope all stores or this store selected; country list
 *    trimmed to the store's selling countries (none left → not offered); hidden by the
 *    store → left out (unless includeHidden for the vendor's read-only tab);
 *  - a store coupon with the same code (case-insensitive) shadows the central one;
 *  - $countryId keeps only coupons usable there (no countries = everywhere).
 */
class CouponCatalog
{
    public function __construct(
        private readonly CouponRepositoryContract $central,
        private readonly StoreCouponRepositoryContract $store,
        private readonly HiddenPromotionRepositoryContract $hidden,
    ) {}

    /**
     * @return Collection<int, PromotionCoupon> store coupons first, then central
     */
    public function all(?int $countryId = null, bool $includeHidden = false): Collection
    {
        return $this->merged(null, $includeHidden)
            ->when($countryId !== null, fn (Collection $coupons) => $coupons->filter(fn (PromotionCoupon $coupon): bool => $coupon->appliesToCountry($countryId)))
            ->values();
    }

    /**
     * The coupon a shopper typed (store coupon wins over a central one with the same code).
     */
    public function findByCode(string $code): ?PromotionCoupon
    {
        $code = trim($code);

        return $code === '' ? null : $this->merged($code, false)->first();
    }

    /**
     * @return Collection<int, PromotionCoupon>
     */
    private function merged(?string $code, bool $includeHidden): Collection
    {
        $tenant = StoreContext::tenant();
        $own = $this->store->listing($code)->map(fn (StoreCoupon $coupon): PromotionCoupon => $this->fromStore($coupon));
        $ownCodes = $own->mapWithKeys(fn (PromotionCoupon $coupon): array => [mb_strtolower($coupon->code) => true]);
        $hidden = array_flip($this->hidden->hiddenIds(PromotionKind::Coupon));

        $central = $this->central->applicableToTenant((int) $tenant->getTenantKey(), $code)
            ->map(function (Coupon $coupon) use ($tenant, $hidden, $ownCodes, $includeHidden): ?PromotionCoupon {
                $isHidden = isset($hidden[$coupon->id]);
                $countries = StoreContext::trimCountries($tenant, $coupon->countries->pluck('id')->map(static fn ($id): int => (int) $id)->all());

                if ($countries === null || ($isHidden && ! $includeHidden) || $ownCodes->has(mb_strtolower($coupon->code))) {
                    return null;
                }

                return $this->fromCentral($coupon, $countries, $isHidden);
            })
            ->filter();

        return $own->concat($central)->values();
    }

    /**
     * @param  list<int>  $countryIds
     */
    private function fromCentral(Coupon $coupon, array $countryIds, bool $hidden): PromotionCoupon
    {
        return new PromotionCoupon(
            source: DiscountSource::Central,
            id: $coupon->id,
            code: $coupon->code,
            name: $coupon->name,
            type: $coupon->type,
            percentage: (float) $coupon->percentage,
            amount: $coupon->amount_minor,
            maxDiscount: $coupon->max_discount_minor,
            minSpend: $coupon->min_spend_minor ?? Money::zero(),
            usageLimit: $coupon->usage_limit,
            perCustomerLimit: $coupon->per_customer_limit,
            firstOrderOnly: $coupon->first_order_only,
            startsAt: $coupon->starts_at !== null ? CarbonImmutable::instance($coupon->starts_at) : null,
            endsAt: $coupon->ends_at !== null ? CarbonImmutable::instance($coupon->ends_at) : null,
            active: $coupon->is_active,
            countryIds: $countryIds,
            hidden: $hidden,
        );
    }

    private function fromStore(StoreCoupon $coupon): PromotionCoupon
    {
        return new PromotionCoupon(
            source: DiscountSource::Vendor,
            id: $coupon->id,
            code: $coupon->code,
            name: $coupon->name,
            type: $coupon->type,
            percentage: (float) $coupon->percentage,
            amount: $coupon->amount_minor,
            maxDiscount: $coupon->max_discount_minor,
            minSpend: $coupon->min_spend_minor ?? Money::zero(),
            usageLimit: $coupon->usage_limit,
            perCustomerLimit: $coupon->per_customer_limit,
            firstOrderOnly: $coupon->first_order_only,
            startsAt: $coupon->starts_at !== null ? CarbonImmutable::instance($coupon->starts_at) : null,
            endsAt: $coupon->ends_at !== null ? CarbonImmutable::instance($coupon->ends_at) : null,
            active: $coupon->is_active,
            countryIds: $coupon->countryIds(),
        );
    }
}
