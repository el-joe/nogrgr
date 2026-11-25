<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * An inactive template is skipped: the built-in default message is sent instead.
 */
enum EmailTemplateStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
}
