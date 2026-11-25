<?php

namespace App\Modules\Commerce\DTOs\Checkout;

use App\Shared\DTOs\BaseDTO;

/**
 * Address choice of the checkout step (spec 06 §2).
 */
final class CheckoutSelectionData extends BaseDTO
{
    public function __construct(
        public readonly ?int $shippingAddressId = null,
        public readonly ?int $billingAddressId = null,
        public readonly bool $billingSameAsShipping = true,
    ) {}
}
