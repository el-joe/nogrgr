<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * @extends RepositoryContract<Cart>
 */
interface CartRepositoryContract extends RepositoryContract
{
    public function findByToken(string $token): ?Cart;

    public function findForCustomer(int $customerId): ?Cart;

    public function findItem(Cart $cart, int $itemId): ?CartItem;

    public function findLine(Cart $cart, int $storeProductId, int $variantKey): ?CartItem;

    public function putLine(Cart $cart, int $storeProductId, int $variantKey, int $quantity): CartItem;

    public function deleteItem(CartItem $item): void;

    public function clear(Cart $cart): void;

    public function touch(Cart $cart): void;
}
