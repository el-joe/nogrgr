<?php

namespace App\Modules\Inventory\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a branch. `defaultCountryIds`: countries this branch becomes the
 * default fulfilment branch of; `zoneIds`: shipping zones it serves.
 */
final class BranchData extends BaseDTO
{
    /**
     * @param  array<string, string>  $name
     * @param  list<int>  $defaultCountryIds
     * @param  list<int>  $zoneIds
     */
    public function __construct(
        public readonly array $name,
        public readonly string $code,
        public readonly int $countryId,
        public readonly ?int $cityId,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $pickupHours,
        public readonly bool $allowsPickup,
        public readonly bool $isActive,
        public readonly array $defaultCountryIds = [],
        public readonly array $zoneIds = [],
    ) {}
}
