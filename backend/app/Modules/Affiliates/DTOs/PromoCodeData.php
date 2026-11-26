<?php

namespace App\Modules\Affiliates\DTOs;

use App\Modules\Affiliates\Enums\RateType;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * ADM-063 promo code: values in basis points (percentage) or USD cents (fixed).
 */
final class PromoCodeData extends BaseDTO
{
    public function __construct(
        public readonly int $affiliateId,
        public readonly string $code,
        public readonly RateType $discountType,
        public readonly int $discountValue,
        public readonly Carbon $endsAt,
        public readonly ?RateType $commissionType = null,
        public readonly ?int $commissionValue = null,
        public readonly int $minSpend = 0,
        public readonly ?int $maxUses = null,
        public readonly bool $isActive = true,
    ) {}
}
