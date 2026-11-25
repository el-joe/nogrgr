<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * Variant of an OWN store product (tenant DB): title, price, stock. Central variants are
 * never copied (read from the central DB, overrides in variant_overrides).
 *
 * @property int $id
 * @property int $store_product_id
 * @property string|null $title
 * @property string|null $sku
 * @property Money|null $price
 * @property Money|null $sale_price
 * @property int|null $stock
 * @property int|null $weight_grams
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StoreProduct $product
 */
class StoreProductVariant extends Model implements AiTranslatable
{
    use HasTranslations;

    protected $table = 'store_product_variants';

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_product_id', 'title', 'sku', 'price', 'sale_price',
        'stock', 'weight_grams', 'is_active', 'position',
    ];

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':USD',
            'sale_price' => MoneyCast::class.':USD',
            'stock' => 'integer',
            'weight_grams' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StoreProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }

    /**
     * Own variant sell price: sale price when > 0, else price (ORDER_FORMULAS §1).
     */
    public function ownSellPrice(): ?Money
    {
        if ($this->sale_price !== null && $this->sale_price->isPositive()) {
            return $this->sale_price;
        }

        return $this->price;
    }
}
