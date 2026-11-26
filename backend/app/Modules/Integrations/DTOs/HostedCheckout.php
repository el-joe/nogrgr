<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * A created checkout: the gateway's id (Stripe session / PaymentIntent id, PayPal order
 * id) and either the hosted page the payer is sent to, or — for the inline Stripe card
 * (decision #78 b) — no URL and the PaymentIntent client secret Stripe.js confirms with.
 */
final class HostedCheckout extends BaseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $redirectUrl,
        public readonly ?string $clientSecret = null,
    ) {}

    public function isInline(): bool
    {
        return $this->clientSecret !== null;
    }
}
