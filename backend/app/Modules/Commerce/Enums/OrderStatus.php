<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Order lifecycle (state machine):
 *
 *   pending_payment → paid → processing → shipped → delivered → completed
 *   pending_payment → cancelled (customer / timeout / failed payment)
 *   paid | processing → cancelled (refund follows) · paid … completed → refunded
 *   delivered | completed → returned
 *
 * COD orders skip `paid` (pending_payment → processing); they are paid on delivery.
 */
enum OrderStatus: string
{
    use HasEnumHelpers;

    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Returned = 'returned';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Processing, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled, self::Refunded],
            self::Processing => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Completed, self::Refunded, self::Returned],
            self::Completed => [self::Refunded, self::Returned],
            self::Cancelled => [self::Refunded],
            self::Refunded, self::Returned => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->transitions() === [];
    }

    /**
     * The customer may still cancel the order themselves (before shipping).
     */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::PendingPayment, self::Paid, self::Processing], true);
    }
}
