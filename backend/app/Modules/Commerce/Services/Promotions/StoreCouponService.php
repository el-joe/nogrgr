<?php

namespace App\Modules\Commerce\Services\Promotions;

use App\Modules\Commerce\DTOs\CouponData;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\PromotionKind;
use App\Modules\Commerce\Events\PromotionsChanged;
use App\Modules\Commerce\Models\StoreCoupon;
use App\Modules\Commerce\Repositories\Contracts\StoreCouponRepositoryContract;
use Illuminate\Support\Facades\DB;

/**
 * Store-owned coupons (VEN-040 service API; the vendor pages come later). Runs in the
 * store's tenant context.
 */
class StoreCouponService
{
    public function __construct(private readonly StoreCouponRepositoryContract $coupons) {}

    public function create(CouponData $data): StoreCoupon
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        $coupon = DB::connection((new StoreCoupon)->getConnectionName())->transaction(function () use ($data): StoreCoupon {
            /** @var StoreCoupon $coupon */
            $coupon = $this->coupons->create($data->attributes());
            $this->coupons->syncCountries($coupon, $data->countryIds);

            return $coupon;
        });

        PromotionsChanged::dispatch(PromotionKind::Coupon, DiscountSource::Vendor, $coupon->id, $tenantId);

        return $coupon;
    }

    public function update(StoreCoupon $coupon, CouponData $data): StoreCoupon
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();

        DB::connection($coupon->getConnectionName())->transaction(function () use ($coupon, $data): void {
            $this->coupons->update($coupon, $data->attributes());
            $this->coupons->syncCountries($coupon, $data->countryIds);
        });

        PromotionsChanged::dispatch(PromotionKind::Coupon, DiscountSource::Vendor, $coupon->id, $tenantId);

        return $coupon;
    }

    public function delete(StoreCoupon $coupon): void
    {
        $tenantId = (int) StoreContext::tenant()->getTenantKey();
        $this->coupons->delete($coupon);

        PromotionsChanged::dispatch(PromotionKind::Coupon, DiscountSource::Vendor, $coupon->id, $tenantId);
    }

    public function codeTaken(string $code, ?int $ignoreId = null): bool
    {
        return $this->coupons->codeExists($code, $ignoreId);
    }
}
