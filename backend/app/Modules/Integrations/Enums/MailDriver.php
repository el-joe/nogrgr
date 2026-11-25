<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Outgoing mail transport chosen in Admin › Integrations › Mail.
 */
enum MailDriver: string
{
    use HasEnumHelpers;

    case Smtp = 'smtp';
    case Log = 'log';
}
