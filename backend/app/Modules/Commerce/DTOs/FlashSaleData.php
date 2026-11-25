<?php

namespace App\Modules\Commerce\DTOs;

use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Shared\DTOs\BaseDTO;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Create / edit of a flash sale (central ADM-026a or a store sale). Central sales list
 * central product ids; store sales list typed product refs.
 */
final class FlashSaleData extends BaseDTO
{
    /**
     * @param  array<string, string>  $title  locale => title
     * @param  list<int>  $countryIds
     * @param  list<int>  $productIds  central product ids (central sales)
     * @param  list<ProductRef>  $products  typed refs (store sales)
     * @param  list<int>  $tenantIds
     */
    public function __construct(
        public readonly array $title,
        public readonly float $discountPercentage,
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
        public readonly bool $isActive = true,
        public readonly array $countryIds = [],
        public readonly array $productIds = [],
        public readonly array $products = [],
        public readonly PromotionScope $storeScope = PromotionScope::AllStores,
        public readonly array $tenantIds = [],
        public readonly ?UploadedFile $banner = null,
        public readonly bool $removeBanner = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'discount_percentage' => $this->discountPercentage,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'is_active' => $this->isActive,
        ];
    }
}
