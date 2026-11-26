<?php

namespace App\Modules\Affiliates\DTOs;

use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * ADM-060b / 062 "Pay out": amount in USD cents, method, transfer reference, notes and
 * an optional proof file.
 */
final class PayoutData extends BaseDTO
{
    public function __construct(
        public readonly int $amount,
        public readonly PayoutMethod $method,
        public readonly ?string $reference = null,
        public readonly ?string $notes = null,
        public readonly ?UploadedFile $attachment = null,
    ) {}
}
