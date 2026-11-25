<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Repositories\Contracts\CartRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<Cart>
 */
class CartRepository extends BaseRepository implements CartRepositoryContract
{
    protected function model(): string
    {
        return Cart::class;
    }

    public function findByToken(string $token): ?Cart
    {
        return $this->query()->where('token', $token)->first();
    }

    public function findForCustomer(int $customerId): ?Cart
    {
        return $this->query()->where('customer_id', $customerId)->first();
    }

    public function findItem(Cart $cart, int $itemId): ?CartItem
    {
        return $cart->items()->whereKey($itemId)->first();
    }

    public function findLine(Cart $cart, int $storeProductId, int $variantKey): ?CartItem
    {
        return $cart->items()->where('store_product_id', $storeProductId)->where('variant_key', $variantKey)->first();
    }

    public function putLine(Cart $cart, int $storeProductId, int $variantKey, int $quantity): CartItem
    {
        return CartItem::query()->updateOrCreate(
            ['cart_id' => $cart->id, 'store_product_id' => $storeProductId, 'variant_key' => $variantKey],
            ['quantity' => $quantity],
        );
    }

    public function deleteItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->forceFill(['coupon_code' => null])->save();
    }

    public function touch(Cart $cart): void
    {
        $cart->forceFill(['last_activity_at' => now()])->save();
    }
}
