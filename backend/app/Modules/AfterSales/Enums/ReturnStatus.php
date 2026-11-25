<?php

namespace App\Modules\AfterSales\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Return request state machine (the one place its transitions are defined):
 *
 *   pending ⇄ awaiting_info → approved → item_received → inspected → refunded → closed
 *                                                                   → exchange_shipped → exchanged → closed
 *   pending | awaiting_info → rejected | cancelled → closed
 *   approved → rejected (item never arrived) · inspected → rejected (inspection failed)
 */
enum ReturnStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case AwaitingInfo = 'awaiting_info';
    case Approved = 'approved';
    case ItemReceived = 'item_received';
    case Inspected = 'inspected';
    case Refunded = 'refunded';
    case ExchangeShipped = 'exchange_shipped';
    case Exchanged = 'exchanged';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Closed = 'closed';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::AwaitingInfo, self::Approved, self::Rejected, self::Cancelled],
            self::AwaitingInfo => [self::Pending, self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::ItemReceived, self::Rejected],
            self::ItemReceived => [self::Inspected],
            self::Inspected => [self::Refunded, self::ExchangeShipped, self::Rejected],
            self::ExchangeShipped => [self::Exchanged],
            self::Refunded, self::Exchanged, self::Rejected, self::Cancelled => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Refunded, self::Exchanged, self::Rejected, self::Cancelled, self::Closed], true);
    }

    /**
     * The quantity of the order line is held by this return (not rejected / cancelled).
     */
    public function holdsQuantity(): bool
    {
        return ! in_array($this, [self::Rejected, self::Cancelled], true);
    }

    /**
     * Progress step (1-6) of the detail header: requested, approved, received, inspection,
     * refund / exchange, closed.
     */
    public function step(): int
    {
        return match ($this) {
            self::Pending, self::AwaitingInfo => 1,
            self::Approved => 2,
            self::ItemReceived => 3,
            self::Inspected => 4,
            self::Refunded, self::ExchangeShipped, self::Exchanged => 5,
            self::Rejected, self::Cancelled, self::Closed => 6,
        };
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(static fn (self $s): string => $s->value, array_filter(self::cases(), static fn (self $s): bool => $s->isOpen())));
    }
}
