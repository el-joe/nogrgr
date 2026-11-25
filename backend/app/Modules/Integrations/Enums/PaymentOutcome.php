<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What a hosted checkout ended in, as reported by the gateway (return URL check or
 * webhook): paid, still pending (not approved / async method), failed or cancelled.
 */
enum PaymentOutcome: string
{
    use HasEnumHelpers;

    case Paid = 'paid';
    case Pending = 'pending';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
