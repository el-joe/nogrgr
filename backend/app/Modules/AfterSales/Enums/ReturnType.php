<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ReturnType: string
{
    use HasEnumHelpers;

    case Return = 'return';
    case Exchange = 'exchange';
}
