<?php

namespace App\Modules\Shipping\DTOs;

use App\Modules\Shipping\Enums\StopPeriodRecurrence;
use App\Shared\DTOs\BaseDTO;

/**
 * Admin create / edit of a stop period (company / country null = all).
 */
final class StopPeriodData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $shippingCompanyId,
        public readonly ?int $countryId,
        public readonly StopPeriodRecurrence $recurrence,
        public readonly string $startsOn,
        public readonly ?string $endsOn,
        public readonly ?int $weekday,
        public readonly bool $isActive,
    ) {}
}
