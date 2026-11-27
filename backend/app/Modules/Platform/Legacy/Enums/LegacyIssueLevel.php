<?php

namespace App\Modules\Platform\Legacy\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Severity of a validation finding in the migration report.
 */
enum LegacyIssueLevel: string
{
    use HasEnumHelpers;

    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
