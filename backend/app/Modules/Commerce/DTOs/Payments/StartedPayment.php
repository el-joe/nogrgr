<?php

namespace App\Modules\Commerce\DTOs\Payments;

use App\Modules\Commerce\Models\OrderPayment;

/**
 * A started payment attempt: redirect gateways (PayPal) give the hosted page, the inline
 * Stripe card (decision #78 b) the PaymentIntent client secret + publishable key that
 * Stripe.js (theme) or the Stripe SDK (mobile app) confirms with.
 */
final readonly class StartedPayment
{
    public function __construct(
        public OrderPayment $payment,
        public ?string $redirectUrl,
        public ?string $clientSecret = null,
        public ?string $publishableKey = null,
    ) {}
}
