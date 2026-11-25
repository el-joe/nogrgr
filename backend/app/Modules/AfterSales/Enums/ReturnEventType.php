<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ReturnEventType: string
{
    use HasEnumHelpers;

    case Status = 'status';
    case Note = 'note';
    case Message = 'message';
}
