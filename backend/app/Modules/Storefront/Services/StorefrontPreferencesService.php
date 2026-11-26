<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Commerce\DTOs\Cart\CartContextData;
use App\Modules\Commerce\Services\Cart\CartService;
use App\Modules\Storefront\DTOs\StorefrontPreferencesData;
use App\Shared\Services\BaseService;

/**
 * Applies the locale switcher: country + currency into the storefront session and the
 * cart's pricing country (so listing prices and the cart agree). The language itself
 * goes through the shared `?lang=` mechanism on the redirect.
 */
class StorefrontPreferencesService extends BaseService
{
    public function __construct(
        private readonly StorefrontContext $context,
        private readonly CartService $carts,
    ) {}

    public function apply(StorefrontPreferencesData $data, CartContextData $cart): void
    {
        $this->context->remember($data->countryId, $data->currency);

        if ($data->countryId !== null && $this->carts->current($cart) !== null) {
            $this->carts->switchCountry($cart, $data->countryId);
        }
    }
}
