<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * unpaid → paid | void · paid → refunded.
 */
enum InvoiceStatus: string
{
    use HasEnumHelpers;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Void = 'void';
    case Refunded = 'refunded';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Unpaid => [self::Paid, self::Void],
            self::Paid => [self::Refunded],
            self::Void, self::Refunded => [],
        }, true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
            self::Void => 'neutral',
            self::Refunded => 'danger',
        };
    }
}
