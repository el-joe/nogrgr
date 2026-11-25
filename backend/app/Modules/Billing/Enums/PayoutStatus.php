<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Platform → store payout (ADM-052): scheduled → processing → paid; scheduled / processing → cancelled; a paid payout may be cancelled (its ledger posting is reversed).
 */
enum PayoutStatus: string
{
    use HasEnumHelpers;

    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Processing, self::Paid, self::Cancelled],
            self::Processing => [self::Paid, self::Cancelled],
            self::Paid => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Scheduled, self::Processing], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Processing => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
