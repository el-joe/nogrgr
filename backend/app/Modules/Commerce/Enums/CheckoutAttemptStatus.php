<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Idempotency record of one "place order" submit (checkout_attempts).
 */
enum CheckoutAttemptStatus: string
{
    use HasEnumHelpers;

    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
