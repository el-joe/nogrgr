<?php

namespace App\Modules\Content\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum FaqGroup: string
{
    use HasEnumHelpers;

    case General = 'general';
    case Stores = 'stores';
    case Billing = 'billing';
    case Technical = 'technical';
}
