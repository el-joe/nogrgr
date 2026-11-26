<?php

namespace App\Modules\Storefront\DTOs;

use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Storefront\Enums\BannerPlacement;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Create / edit input of a central banner (ADM-085a) or a store banner (VEN-035a).
 * Texts are locale => value maps; images are optional on edit (null = keep).
 * Store scope / tenants apply to central banners only.
 */
final readonly class BannerInputData
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $subtitle
     * @param  array<string, string>  $ctaLabel
     * @param  list<int>  $countryIds
     * @param  list<int>  $tenantIds
     */
    public function __construct(
        public BannerPlacement $placement,
        public array $title,
        public array $subtitle = [],
        public array $ctaLabel = [],
        public ?string $linkUrl = null,
        public bool $isActive = true,
        public ?CarbonImmutable $startsAt = null,
        public ?CarbonImmutable $endsAt = null,
        public array $countryIds = [],
        public PromotionScope $storeScope = PromotionScope::AllStores,
        public array $tenantIds = [],
        public ?UploadedFile $image = null,
        public ?UploadedFile $mobileImage = null,
        public bool $removeMobileImage = false,
    ) {}
}
