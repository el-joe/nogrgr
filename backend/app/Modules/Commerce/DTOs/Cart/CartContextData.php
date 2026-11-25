<?php

namespace App\Modules\Commerce\DTOs\Cart;

use App\Modules\Identity\Models\Customer;
use App\Shared\DTOs\BaseDTO;

/**
 * Who is asking for a cart: the guest cart token (X-Cart-Token header / session) and the
 * signed-in customer (session or Sanctum token). A token together with a customer merges
 * the guest cart into the customer's cart.
 */
final class CartContextData extends BaseDTO
{
    public function __construct(
        public readonly ?string $token = null,
        public readonly ?Customer $customer = null,
        public readonly ?string $displayCurrency = null,
    ) {}
}
