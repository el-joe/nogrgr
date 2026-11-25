<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Brand request (vendor asks for a private-label brand / logo / registration):
 * pending → under_review → awaiting_payment → in_progress → completed; rejected / cancelled.
 */
enum BrandRequestStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case UnderReview = 'under_review';
    case AwaitingPayment = 'awaiting_payment';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::UnderReview, self::InProgress => 'info',
            self::AwaitingPayment => 'warning',
            self::Completed => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(static fn (self $status): string => $status->value, array_filter(self::cases(), static fn (self $status): bool => $status->isOpen())));
    }
}
