<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum TextDirection: string
{
    use HasEnumHelpers;

    case Ltr = 'ltr';
    case Rtl = 'rtl';
}
