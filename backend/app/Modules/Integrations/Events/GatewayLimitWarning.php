<?php

namespace App\Modules\Integrations\Events;

use App\Modules\Integrations\Models\GatewayLimitUsage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A store passed the warning level (default 80 %) of its monthly volume limit on a
 * platform gateway account (MAIL-023, once per store × gateway × month).
 * Listener: SendGatewayLimitNotifications.
 */
final class GatewayLimitWarning
{
    use Dispatchable;

    public function __construct(
        public readonly GatewayLimitUsage $usage,
        public readonly int $limitMinor,
    ) {}
}
