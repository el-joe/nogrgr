<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a verified gateway webhook did: applied a status change, was a replay of an
 * event already handled, or was not about a payment we know (ignored, still 200).
 */
enum WebhookResult: string
{
    use HasEnumHelpers;

    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Ignored = 'ignored';
}
