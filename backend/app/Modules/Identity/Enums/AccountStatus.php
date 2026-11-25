<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum AccountStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
