<?php

namespace App\Modules\Catalog\DTOs\Vendor;

use App\Modules\Commerce\Enums\ProfitType;
use App\Modules\Commerce\Pricing\ProfitRule;
use App\Shared\DTOs\BaseDTO;

/**
 * A markup to apply (price list modal, VEN-010a): type + value, optionally limited to one
 * country and/or one store variant (null = all).
 */
final class MarkupData extends BaseDTO
{
    public function __construct(
        public readonly ProfitType $profitType,
        public readonly float $profitValue,
        public readonly ?int $countryId = null,
        public readonly ?int $variantId = null,
    ) {}

    public function rule(): ProfitRule
    {
        return new ProfitRule($this->profitType, $this->profitValue);
    }
}
