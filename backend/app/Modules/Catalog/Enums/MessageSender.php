<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who wrote a request chat message.
 */
enum MessageSender: string
{
    use HasEnumHelpers;

    case Admin = 'admin';
    case Vendor = 'vendor';
}
