<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Stock of one central product (variant_key 0) or variant in one branch.
 * available = on_hand − reserved (never below 0).
 *
 * @property int $id
 * @property int $branch_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $variant_key
 * @property int $on_hand
 * @property int $reserved
 * @property-read Branch $branch
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 */
class BranchStock extends Model
{
    use CentralConnection;

    protected $table = 'branch_stock';

    /**
     * @var list<string>
     */
    protected $fillable = ['branch_id', 'product_id', 'product_variant_id', 'variant_key', 'on_hand', 'reserved'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'variant_key' => 'integer',
            'on_hand' => 'integer',
            'reserved' => 'integer',
        ];
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
