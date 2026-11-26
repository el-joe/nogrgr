<?php

namespace App\Modules\Storefront\Enums;

/**
 * Where the customer lands after the gateway sends them back (SF-ECO-023b):
 * paid / cancelled / pending → order page with a toast; failed → "Payment was not completed".
 */
enum PaymentReturnOutcome: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * Session flash key read by x-ui::toasts.
     */
    public function flashKey(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending, self::Cancelled => 'info',
            self::Failed => 'error',
        };
    }

    public function message(): string
    {
        return __st('checkout.return.'.$this->value);
    }
}
