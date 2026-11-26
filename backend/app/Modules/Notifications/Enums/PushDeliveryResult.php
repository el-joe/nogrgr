<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Outcome of one FCM send: delivered to FCM, token no longer valid (cleared), or failed.
 */
enum PushDeliveryResult: string
{
    use HasEnumHelpers;

    case Sent = 'sent';
    case InvalidToken = 'invalid_token';
    case Failed = 'failed';
    case NotConfigured = 'not_configured';
}
