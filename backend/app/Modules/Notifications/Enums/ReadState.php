<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Inbox read filter.
 */
enum ReadState: string
{
    use HasEnumHelpers;

    case Unread = 'unread';
    case Read = 'read';
}
