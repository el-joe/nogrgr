<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Vendor profile › Overview › Edit store info.
 */
final class VendorStoreData extends BaseDTO
{
    /**
     * @param  list<int>  $sellingCountryIds
     */
    public function __construct(
        public readonly string $name,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly ?string $ownerPhone = null,
        public readonly ?int $defaultCountryId = null,
        public readonly array $sellingCountryIds = [],
    ) {}
}
