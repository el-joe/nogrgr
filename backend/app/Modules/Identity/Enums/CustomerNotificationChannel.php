<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Delivery channels customers control (database notifications always stay on).
 */
enum CustomerNotificationChannel: string
{
    use HasEnumHelpers;

    case Email = 'email';
    case Push = 'push';
}
