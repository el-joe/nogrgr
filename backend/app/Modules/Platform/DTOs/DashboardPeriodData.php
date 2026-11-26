<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Reporting window (defaults to the last 30 days) and country of the admin dashboard.
 */
final class DashboardPeriodData extends BaseDTO
{
    public function __construct(
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
        public readonly ?int $countryId = null,
    ) {}
}
