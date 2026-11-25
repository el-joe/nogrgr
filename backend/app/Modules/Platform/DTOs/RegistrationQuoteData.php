<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Models\Plan;
use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * Order summary of the payment step (WEB-016c): the plan's first term with the platform
 * tax, computed exactly like the subscription invoice issued at provisioning.
 */
final class RegistrationQuoteData extends BaseDTO
{
    public function __construct(
        public readonly Plan $plan,
        public readonly BillingTerm $term,
        public readonly Money $subtotal,
        public readonly int $taxRateBp,
        public readonly Money $tax,
        public readonly Money $total,
    ) {}

    public function requiresPayment(): bool
    {
        return $this->total->isPositive();
    }
}
