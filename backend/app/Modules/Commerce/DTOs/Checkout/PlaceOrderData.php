<?php

namespace App\Modules\Commerce\DTOs\Checkout;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\DTOs\BaseDTO;

/**
 * "Place order" submit: idempotency key (double submit → same result), address choice
 * and the selected gateway.
 */
final class PlaceOrderData extends BaseDTO
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly PaymentGatewayCode $gateway,
        public readonly ?int $shippingAddressId = null,
        public readonly ?int $billingAddressId = null,
        public readonly bool $billingSameAsShipping = true,
    ) {}

    public function selection(): CheckoutSelectionData
    {
        return new CheckoutSelectionData($this->shippingAddressId, $this->billingAddressId, $this->billingSameAsShipping);
    }

    public function hash(): string
    {
        return hash('sha256', implode('|', [$this->gateway->value, $this->shippingAddressId, $this->billingAddressId, (int) $this->billingSameAsShipping]));
    }
}
