<?php

namespace App\Modules\Affiliates\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * AFF-012a / VEN-055a payout request: amount in USD cents and an optional note.
 */
final class PayoutRequestData extends BaseDTO
{
    public function __construct(
        public readonly int $amount,
        public readonly ?string $note = null,
    ) {}
}
