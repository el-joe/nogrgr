<?php

namespace App\Modules\Billing\DTOs\Finance;

use App\Modules\Billing\Enums\SettlementMethod;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Mark settlement paid (ADM-050a): money the store paid the platform outside the
 * gateway. Amount in USD minor units; applied to the oldest open orders first.
 */
final class ManualSettlementData extends BaseDTO
{
    public function __construct(
        public readonly int $amount,
        public readonly SettlementMethod $method,
        public readonly ?string $reference = null,
        public readonly ?string $note = null,
        public readonly ?UploadedFile $attachment = null,
    ) {}
}
