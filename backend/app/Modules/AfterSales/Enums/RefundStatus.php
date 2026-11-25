<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 *   pending → processing → completed
 *   pending | processing → failed → (retry) processing … | completed (manual) | rejected
 * Pending / processing / failed refunds reserve their amount against the refundable cap.
 */
enum RefundStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function reservesAmount(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::Failed, self::Completed], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::Failed], true);
    }

    /**
     * @return list<string>
     */
    public static function reservingValues(): array
    {
        return array_values(array_map(static fn (self $s): string => $s->value, array_filter(self::cases(), static fn (self $s): bool => $s->reservesAmount())));
    }
}
