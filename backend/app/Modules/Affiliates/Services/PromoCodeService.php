<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\PromoCodeData;
use App\Modules\Affiliates\Exceptions\AffiliateRuleException;
use App\Modules\Affiliates\Models\AffiliatePromoCode;
use App\Modules\Affiliates\Repositories\Contracts\PromoCodeRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Affiliate promo codes (ADM-063): codes are stored upper-case; a code that already
 * attributed a conversion cannot be deleted (deactivate it instead).
 */
class PromoCodeService extends BaseService
{
    public function __construct(private readonly PromoCodeRepositoryContract $codes) {}

    public function create(PromoCodeData $data): AffiliatePromoCode
    {
        /** @var AffiliatePromoCode $code */
        $code = $this->codes->create($this->attributes($data));

        return $code;
    }

    public function update(AffiliatePromoCode $code, PromoCodeData $data): AffiliatePromoCode
    {
        /** @var AffiliatePromoCode $updated */
        $updated = $this->codes->update($code, $this->attributes($data));

        return $updated;
    }

    public function delete(AffiliatePromoCode $code): void
    {
        if ($this->codes->hasConversions($code)) {
            throw AffiliateRuleException::promoCodeInUse();
        }

        $this->codes->delete($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(PromoCodeData $data): array
    {
        return [
            'affiliate_id' => $data->affiliateId,
            'code' => Str::upper(trim($data->code)),
            'discount_type' => $data->discountType,
            'discount_value' => $data->discountValue,
            'commission_type' => $data->commissionType,
            'commission_value' => $data->commissionType !== null ? $data->commissionValue : null,
            'min_spend' => $data->minSpend,
            'max_uses' => $data->maxUses,
            'ends_at' => $data->endsAt->copy()->endOfDay(),
            'is_active' => $data->isActive,
        ];
    }
}
