<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Exceptions\PaymentGatewayException;
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
    ) {
        // USD is the only payment currency (decision #87): every driver gets USD minor units.
        if ($amount->currency !== Money::BASE_CURRENCY) {
            throw PaymentGatewayException::nonBaseCurrency($amount->currency);
        }
    }
}
