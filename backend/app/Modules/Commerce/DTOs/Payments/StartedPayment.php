<?php

namespace App\Modules\Commerce\DTOs\Payments;

use App\Modules\Commerce\Models\OrderPayment;

/**
 * A started payment attempt: redirect gateways return a URL, inline ones (Stripe
 * PaymentIntents) the client secret the card panel confirms with (3-D Secure included).
 */
final readonly class StartedPayment
{
    public function __construct(
        public OrderPayment $payment,
        public ?string $redirectUrl,
        public ?string $clientSecret,
    ) {}
}
