<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum RefundSource: string
{
    use HasEnumHelpers;

    case Cancellation = 'cancellation';
    case Return = 'return';
    case Manual = 'manual';
}
