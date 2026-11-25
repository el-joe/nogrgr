<?php

namespace App\Modules\Commerce\DTOs\Checkout;

use App\Modules\Commerce\DTOs\Cart\CartSummary;
use App\Modules\Commerce\DTOs\Payments\GatewayOption;
use App\Modules\Commerce\Enums\CheckoutBlock;
use App\Modules\Identity\DTOs\CheckoutAddressSelectionData;

/**
 * Everything the checkout page / app needs: addresses, the cart summary for the shipping
 * country, payment methods (spec 08 filtering) and why paying is blocked, if it is.
 */
final readonly class CheckoutOptions
{
    /**
     * @param  list<GatewayOption>  $gateways
     */
    public function __construct(
        public CheckoutAddressSelectionData $addresses,
        public CartSummary $summary,
        public array $gateways,
        public ?CheckoutBlock $block,
    ) {}
}
