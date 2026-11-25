<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * A refund to send to the gateway: the captured transaction (Stripe payment intent /
 * charge id, PayPal capture id), the amount and our reference (idempotency key, so a
 * retried call never refunds twice).
 */
final class RefundRequestData extends BaseDTO
{
    public function __construct(
        public readonly string $transactionId,
        public readonly Money $amount,
        public readonly string $reference,
        public readonly ?string $reason = null,
    ) {}
}
