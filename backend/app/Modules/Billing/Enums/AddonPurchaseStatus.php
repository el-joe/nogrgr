<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * pending_payment → paid (gateway success, P5) | failed | cancelled.
 */
enum AddonPurchaseStatus: string
{
    use HasEnumHelpers;

    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::PendingPayment;
    }

    /**
     * Status pill key for <x-ui::status>.
     */
    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment => 'pending',
            self::Paid => 'paid',
            self::Failed => 'failed',
            self::Cancelled => 'cancelled',
        };
    }
}
