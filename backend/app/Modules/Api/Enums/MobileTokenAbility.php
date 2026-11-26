<?php

namespace App\Modules\Api\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Sanctum abilities of the mobile app tokens: the short-lived access token calls the
 * Mobile API and the Store API; the refresh token can only call `auth/refresh`.
 */
enum MobileTokenAbility: string
{
    use HasEnumHelpers;

    case Access = 'api';
    case Refresh = 'auth:refresh';
}
