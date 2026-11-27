<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How one plan quota is resolved for a store (Admin › Vendor profile › Limit overrides).
 */
enum PlanLimitOverrideMode: string
{
    use HasEnumHelpers;

    /** No override: the plan value applies. */
    case Plan = 'plan';

    /** A fixed number for this store. */
    case Custom = 'custom';

    /** No cap for this store. */
    case Unlimited = 'unlimited';
}
