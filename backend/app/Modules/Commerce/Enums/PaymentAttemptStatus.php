<?php

namespace App\Modules\Commerce\Enums;

use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * One storefront payment attempt (order_payments row, the per-attempt payment log).
 */
enum PaymentAttemptStatus: string
{
    use HasEnumHelpers;

    case Initiated = 'initiated';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public static function fromOutcome(PaymentOutcome $outcome): self
    {
        return match ($outcome) {
            PaymentOutcome::Paid => self::Paid,
            PaymentOutcome::Pending => self::Pending,
            PaymentOutcome::Failed => self::Failed,
            PaymentOutcome::Cancelled => self::Cancelled,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Cancelled], true);
    }
}
