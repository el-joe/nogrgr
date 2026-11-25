<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PlanStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';

    public function tone(): string
    {
        return $this === self::Active ? 'success' : 'neutral';
    }
}
