<?php

namespace App\Modules\Commerce\DTOs\Payments;

use App\Modules\Commerce\Models\OrderPayment;

/**
 * A started payment attempt and the provider's hosted page the payer is sent to (Stripe
 * Checkout / PayPal); every storefront gateway is a redirect (decision #78 b).
 */
final readonly class StartedPayment
{
    public function __construct(
        public OrderPayment $payment,
        public ?string $redirectUrl,
    ) {}
}
