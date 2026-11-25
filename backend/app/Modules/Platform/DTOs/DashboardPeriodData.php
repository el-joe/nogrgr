<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Reporting window of the admin dashboard (defaults to the last 30 days).
 */
final class DashboardPeriodData extends BaseDTO
{
    public function __construct(
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
    ) {}
}
