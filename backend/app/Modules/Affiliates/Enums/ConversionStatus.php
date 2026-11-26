<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * pending (store signed up) → approved (first paid subscription, balance credited) →
 * paid (fully covered by payouts); approved / paid → reversed (refund or admin);
 * pending → rejected (self-referral or admin).
 */
enum ConversionStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Reversed = 'reversed';
    case Rejected = 'rejected';

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved, self::Paid => 'success',
            self::Reversed, self::Rejected => 'danger',
        };
    }

    public function canReverse(): bool
    {
        return in_array($this, [self::Approved, self::Paid], true);
    }

    public function canReject(): bool
    {
        return $this === self::Pending;
    }
}
