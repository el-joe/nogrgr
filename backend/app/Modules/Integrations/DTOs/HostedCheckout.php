<?php

namespace App\Modules\Integrations\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * A created hosted checkout: the gateway's id (Stripe session id / PayPal order id)
 * and the hosted page the payer is sent to.
 */
final class HostedCheckout extends BaseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $redirectUrl,
    ) {}
}
