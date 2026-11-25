<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * A created hosted checkout: the gateway's id (Stripe session id / PayPal order id)
 * and the page the payer is sent to (empty for inline gateways, which return the client
 * secret the card panel confirms with instead).
 */
final class HostedCheckout extends BaseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $redirectUrl,
        public readonly ?string $clientSecret = null,
    ) {}
}
