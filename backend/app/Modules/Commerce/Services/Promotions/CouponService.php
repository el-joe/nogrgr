<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\DTOs\CouponData;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Repositories\Contracts\CouponRepositoryContract;
use Illuminate\Support\Facades\DB;

/**
 * Central coupons (ADM-025 / 025a): create, edit, switch, delete (soft, so order coupon
 * references stay valid). Stores read them live through CouponCatalog.
 */
class CouponService
{
    public function __construct(private readonly CouponRepositoryContract $coupons) {}

    public function create(CouponData $data): Coupon
    {
        $coupon = DB::connection((new Coupon)->getConnectionName())->transaction(function () use ($data): Coupon {
            /** @var Coupon $coupon */
            $coupon = $this->coupons->create([...$data->attributes(), 'store_scope' => $data->storeScope]);
            $this->coupons->syncTargets($coupon, $data->countryIds, $data->tenantIds);

            return $coupon;
        });

        $this->changed($coupon);

        return $coupon;
    }

    public function update(Coupon $coupon, CouponData $data): Coupon
    {
        DB::connection($coupon->getConnectionName())->transaction(function () use ($coupon, $data): void {
            $this->coupons->update($coupon, [...$data->attributes(), 'store_scope' => $data->storeScope]);
            $this->coupons->syncTargets($coupon, $data->countryIds, $data->tenantIds);
        });

        $this->changed($coupon);

        return $coupon;
    }

    public function setActive(Coupon $coupon, bool $active): void
    {
        $this->coupons->update($coupon, ['is_active' => $active]);
        $this->changed($coupon);
    }

    public function delete(Coupon $coupon): void
    {
        $this->coupons->delete($coupon);
        $this->changed($coupon);
    }

    public function usage(Coupon $coupon): int
    {
        return $this->coupons->usageCount($coupon->id);
    }

    /**
     * @return array<int, string>
     */
    public function countryTabs(): array
    {
        return $this->coupons->targetedCountries();
    }

    private function changed(Coupon $coupon): void
    {
        PromotionsChanged::dispatch(PromotionKind::Coupon, DiscountSource::Central, $coupon->id);
    }
}
