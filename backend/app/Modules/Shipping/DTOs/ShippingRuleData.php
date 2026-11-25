<?php

namespace App\Modules\Shipping\DTOs;

use App\Modules\Shipping\Enums\ShippingRuleStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a shipping rule with its zones and weight bands (prices in USD minor units).
 */
final class ShippingRuleData extends BaseDTO
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<array{id: int|null, name: string, city_ids: list<int>, bands: list<array{name: string, min_weight_grams: int, max_weight_grams: int|null, price: int}>}>  $zones
     */
    public function __construct(
        public readonly string $name,
        public readonly ShippingRuleStatus $status,
        public readonly array $countryIds,
        public readonly array $zones,
    ) {}
}
