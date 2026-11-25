<?php

namespace App\Modules\Shipping\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a country's fixed shipping rate (USD per gram, up to 6 decimals).
 */
final class FixedShippingCostData extends BaseDTO
{
    public function __construct(
        public readonly int $countryId,
        public readonly string $pricePerGram,
    ) {}
}
