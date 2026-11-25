<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum UrlScheme: string
{
    use HasEnumHelpers;

    case Https = 'https';
    case Http = 'http';
}
