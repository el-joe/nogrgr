<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;
use App\Shared\Money\Money;

/**
 * One hosted checkout: the amount (USD minor units), our reference (payment log
 * reference, echoed back by the gateway), what the payer sees and where the gateway
 * returns the payer after paying / cancelling.
 */
final class CheckoutRequestData extends BaseDTO
{
    public function __construct(
        public readonly Money $amount,
        public readonly string $reference,
        public readonly string $description,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly ?string $customerEmail = null,
        public readonly ?string $locale = null,
    ) {}
}
