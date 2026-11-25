<?php

namespace App\Modules\Catalog\DTOs\Requests;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * New payment request on a manufacturing / brand request. Amount in USD minor units.
 */
final class PaymentRequestData extends BaseDTO
{
    public function __construct(
        public readonly string $label,
        public readonly int $amountMinor,
        public readonly ?Carbon $dueAt = null,
        public readonly ?string $note = null,
    ) {}
}
