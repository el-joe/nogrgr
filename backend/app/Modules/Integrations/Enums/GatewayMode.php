<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum GatewayMode: string
{
    use HasEnumHelpers;

    case Test = 'test';
    case Live = 'live';
}
