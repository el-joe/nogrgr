<?php

namespace App\Modules\Commerce\DTOs;

use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Shared\DTOs\BaseDTO;
use Carbon\CarbonImmutable;

/**
 * Create / edit of a coupon (central ADM-025a, or a store coupon). Money in USD minor
 * units; no countries = every country. Store scope / tenant ids only apply centrally.
 */
final class CouponData extends BaseDTO
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<int>  $tenantIds
     */
    public function __construct(
        public readonly string $code,
        public readonly CouponType $type,
        public readonly ?float $percentage = null,
        public readonly ?int $amountMinor = null,
        public readonly ?int $maxDiscountMinor = null,
        public readonly int $minSpendMinor = 0,
        public readonly ?int $usageLimit = null,
        public readonly ?int $perCustomerLimit = null,
        public readonly bool $firstOrderOnly = false,
        public readonly ?CarbonImmutable $startsAt = null,
        public readonly ?CarbonImmutable $endsAt = null,
        public readonly bool $isActive = true,
        public readonly ?string $name = null,
        public readonly array $countryIds = [],
        public readonly PromotionScope $storeScope = PromotionScope::AllStores,
        public readonly array $tenantIds = [],
    ) {}

    /**
     * Column values shared by central and store coupons.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'percentage' => $this->type === CouponType::Percentage ? $this->percentage : null,
            'amount_minor' => $this->type === CouponType::Fixed ? $this->amountMinor : null,
            'max_discount_minor' => $this->type === CouponType::Percentage ? $this->maxDiscountMinor : null,
            'min_spend_minor' => $this->minSpendMinor,
            'usage_limit' => $this->usageLimit,
            'per_customer_limit' => $this->perCustomerLimit,
            'first_order_only' => $this->firstOrderOnly,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'is_active' => $this->isActive,
        ];
    }
}
