<?php

namespace App\Modules\Integrations\Events;

use App\Modules\Integrations\Models\GatewayLimitUsage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A store reached 100 % of its monthly volume limit on a platform gateway account:
 * the gateway is hidden at its checkout until next month, an admin unblock or own keys
 * (MAIL-024, once per store × gateway × month).
 * Listener: SendGatewayLimitNotifications.
 */
final class GatewayLimitBlocked
{
    use Dispatchable;

    public function __construct(
        public readonly GatewayLimitUsage $usage,
        public readonly int $limitMinor,
    ) {}
}
