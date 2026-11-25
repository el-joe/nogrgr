<?php

namespace App\Modules\Content\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum PageTarget: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Storefront = 'storefront';
    case Both = 'both';
}
