<?php

namespace App\Modules\Commerce\DTOs\Promotions;

use App\Modules\Commerce\Enums\DiscountSource;
use App\Modules\Commerce\Enums\FlashSaleStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A flash sale as a store sees it (central or the store's own), tagged with its source.
 * countryIds = [] means every country; products are typed refs.
 */
final readonly class PromotionFlashSale
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<ProductRef>  $products
     */
    public function __construct(
        public DiscountSource $source,
        public int $id,
        public string $title,
        public float $percentage,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $active,
        public ?string $bannerUrl,
        public array $countryIds = [],
        public array $products = [],
        public bool $hidden = false,
    ) {}

    public function key(): string
    {
        return $this->source->value.':'.$this->id;
    }

    public function appliesToCountry(?int $countryId): bool
    {
        return $this->countryIds === [] || ($countryId !== null && in_array($countryId, $this->countryIds, true));
    }

    public function isLive(?CarbonInterface $at = null): bool
    {
        return $this->status($at) === FlashSaleStatus::Live;
    }

    public function status(?CarbonInterface $at = null): FlashSaleStatus
    {
        $at ??= CarbonImmutable::now();

        return match (true) {
            ! $this->active => FlashSaleStatus::Inactive,
            $this->startsAt->greaterThan($at) => FlashSaleStatus::Scheduled,
            $this->endsAt->lessThan($at) => FlashSaleStatus::Ended,
            default => FlashSaleStatus::Live,
        };
    }

    public function covers(ProductRef $product): bool
    {
        foreach ($this->products as $ref) {
            if ($ref->source === $product->source && $ref->id === $product->id) {
                return true;
            }
        }

        return false;
    }
}
