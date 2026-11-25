<?php

namespace App\Modules\Commerce\DTOs\Promotions;

use App\Modules\Commerce\Enums\CouponStatus;
use App\Modules\Commerce\Enums\CouponType;
use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Pricing\AppliedCoupon;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A coupon as a store sees it: central (admin) or the store's own, merged by
 * CouponCatalog and tagged with its source (decision #65 b). countryIds = [] means every
 * country (central ones already trimmed to the store's selling countries).
 */
final readonly class PromotionCoupon
{
    /**
     * @param  list<int>  $countryIds
     */
    public function __construct(
        public DiscountSource $source,
        public int $id,
        public string $code,
        public ?string $name,
        public CouponType $type,
        public float $percentage,
        public ?Money $amount,
        public ?Money $maxDiscount,
        public Money $minSpend,
        public ?int $usageLimit,
        public ?int $perCustomerLimit,
        public bool $firstOrderOnly,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public bool $active,
        public array $countryIds = [],
        public bool $hidden = false,
    ) {}

    public function key(): string
    {
        return $this->source->value.':'.$this->id;
    }

    public function isCentral(): bool
    {
        return $this->source === DiscountSource::Central;
    }

    public function appliesToCountry(?int $countryId): bool
    {
        return $this->countryIds === [] || ($countryId !== null && in_array($countryId, $this->countryIds, true));
    }

    public function isWithinWindow(?CarbonInterface $at = null): bool
    {
        $at ??= CarbonImmutable::now();

        return ($this->startsAt === null || $this->startsAt->lessThanOrEqualTo($at))
            && ($this->endsAt === null || $this->endsAt->greaterThanOrEqualTo($at));
    }

    public function status(int $used = 0, ?CarbonInterface $at = null): CouponStatus
    {
        $at ??= CarbonImmutable::now();

        return match (true) {
            ! $this->active => CouponStatus::Inactive,
            $this->usageLimit !== null && $used >= $this->usageLimit => CouponStatus::UsedUp,
            $this->startsAt !== null && $this->startsAt->greaterThan($at) => CouponStatus::Scheduled,
            $this->endsAt !== null && $this->endsAt->lessThan($at) => CouponStatus::Expired,
            default => CouponStatus::Active,
        };
    }

    /**
     * The engine's view of this coupon (OrderComposer / OrderPricing::couponDiscount).
     */
    public function applied(): AppliedCoupon
    {
        return new AppliedCoupon($this->type, $this->source, $this->percentage, $this->amount, $this->maxDiscount, $this->code, $this->id);
    }
}
