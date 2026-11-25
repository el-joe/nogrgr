<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\PaymentOutcome;
use App\Shared\DTOs\BaseDTO;

/**
 * The gateway's verdict on one checkout. `transactionId` is the capture / payment
 * intent id when paid; `reason` is the gateway's decline text when it gives one.
 */
final class PaymentResult extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $details  safe subset of the gateway response (no secrets)
     */
    public function __construct(
        public readonly PaymentOutcome $outcome,
        public readonly string $checkoutId,
        public readonly ?string $transactionId = null,
        public readonly ?string $reason = null,
        public readonly array $details = [],
    ) {}
}
