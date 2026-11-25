<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Billing period of a subscription; a plan has one price per term.
 */
enum BillingTerm: string
{
    use HasEnumHelpers;

    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
