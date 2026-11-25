<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * pending → captured | failed · failed → pending (retry) · captured → refunded.
 */
enum PaymentLogStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextStatuses(), true);
    }

    /**
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Pending => [self::Captured, self::Failed],
            self::Failed => [self::Pending],
            self::Captured => [self::Refunded],
            self::Refunded => [],
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Captured => 'success',
            self::Failed => 'danger',
            self::Refunded => 'neutral',
        };
    }
}
