<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How the current period started (the "Type" column of the subscriptions list).
 */
enum SubscriptionKind: string
{
    use HasEnumHelpers;

    case Registration = 'registration';
    case Renewal = 'renewal';
    case Trial = 'trial';
}
