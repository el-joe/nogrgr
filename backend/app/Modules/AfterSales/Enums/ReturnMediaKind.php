<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ReturnMediaKind: string
{
    use HasEnumHelpers;

    case Photo = 'photo';
    case Video = 'video';
    case Inspection = 'inspection';
}
