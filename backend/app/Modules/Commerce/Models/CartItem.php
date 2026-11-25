<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Models\StoreProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One cart line: a store product and its variant (variant_key = central variant id for
 * central products, store_product_variants id for own products, 0 = no variant).
 *
 * @property int $id
 * @property int $cart_id
 * @property int $store_product_id
 * @property int $variant_key
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StoreProduct|null $storeProduct
 */
class CartItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['cart_id', 'store_product_id', 'variant_key', 'quantity'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cart_id' => 'integer', 'store_product_id' => 'integer', 'variant_key' => 'integer', 'quantity' => 'integer'];
    }

    public function variantId(): ?int
    {
        return $this->variant_key > 0 ? $this->variant_key : null;
    }

    /**
     * Stable key of the line inside the order engine.
     */
    public function lineKey(): string
    {
        return $this->store_product_id.':'.$this->variant_key;
    }

    /**
     * @return BelongsTo<StoreProduct, $this>
     */
    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }
}
