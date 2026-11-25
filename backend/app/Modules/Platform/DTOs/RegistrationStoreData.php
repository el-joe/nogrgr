<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Complete registration › store step (WEB-018a).
 */
final class RegistrationStoreData extends BaseDTO
{
    /**
     * @param  list<int>  $sellingCategoryIds  active top-level catalog categories
     */
    public function __construct(
        public readonly string $storeName,
        public readonly string $slug,
        public readonly ?string $customDomain = null,
        public readonly int $profitPercentBps = 0,
        public readonly array $sellingCategoryIds = [],
    ) {}
}
