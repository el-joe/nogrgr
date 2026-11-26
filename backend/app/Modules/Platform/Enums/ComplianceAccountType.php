<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who holds the store (compliance center, VEN-084): an individual (no commercial
 * registration needed) or a registered company (legal name + commercial registration).
 */
enum ComplianceAccountType: string
{
    use HasEnumHelpers;

    case Individual = 'individual';
    case Company = 'company';
}
