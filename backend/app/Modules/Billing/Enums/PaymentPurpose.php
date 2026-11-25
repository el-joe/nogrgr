<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PaymentPurpose: string
{
    use HasEnumHelpers;

    case Registration = 'registration';
    case Renewal = 'renewal';
    case Addon = 'addon';
    case Settlement = 'settlement';
}
