<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum RefundOutcome: string
{
    use HasEnumHelpers;

    case Completed = 'completed';
    case Pending = 'pending';
    case Failed = 'failed';
}
