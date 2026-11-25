<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Real-time transport for panel notifications: self-hosted Laravel Reverb or Pusher
 * Channels (both speak the Pusher protocol).
 */
enum BroadcastDriver: string
{
    use HasEnumHelpers;

    case Reverb = 'reverb';
    case Pusher = 'pusher';
}
