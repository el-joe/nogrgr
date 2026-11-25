<?php

namespace App\Modules\Shipping\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Day rule of a company in one country; $isDefault makes the company that country's default carrier.
 */
final class DayRuleData extends BaseDTO
{
    public function __construct(
        public readonly int $countryId,
        public readonly int $minDays,
        public readonly int $maxDays,
        public readonly bool $isDefault = false,
    ) {}
}
