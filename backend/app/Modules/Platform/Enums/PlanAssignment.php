<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How provisioning starts the store's subscription (Billing SubscriptionService).
 */
enum PlanAssignment: string
{
    use HasEnumHelpers;

    /** Plan trial (falls back to an active, invoiced subscription when the plan has no trial days). */
    case Trial = 'trial';

    /** Manual billing: active now, invoice settled as a manual payment. */
    case ManualPaid = 'manual_paid';

    /** Active now, invoice left open for the vendor to pay. */
    case Invoiced = 'invoiced';

    /** Paid at sign-up through a gateway: active now, the invoice settled by that payment log. */
    case Prepaid = 'prepaid';
}
