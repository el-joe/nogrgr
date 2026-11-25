<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central product variant (one combination of attribute options).
 *
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property string|null $title
 * @property Money|null $price
 * @property int|null $weight_grams
 * @property array<string, int>|null $fixed_shipping_costs
 * @property int $stock
 * @property int $position
 * @property RecordStatus $status
 * @property string|null $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product $product
 */
class ProductVariant extends Model
{
    use CentralConnection;

    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * @var list<string>
     */
    protected $fillable = ['product_id', 'sku', 'title', 'price', 'weight_grams', 'fixed_shipping_costs', 'stock', 'position', 'status', 'image_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':USD',
            'weight_grams' => 'integer',
            'fixed_shipping_costs' => 'array',
            'stock' => 'integer',
            'position' => 'integer',
            'status' => RecordStatus::class,
        ];
    }

    /**
     * Tenant cost basis of the variant: its own price, else the product's.
     */
    public function costBasis(): Money
    {
        return $this->price ?? $this->product->costBasis();
    }

    /**
     * Effective weight: the variant's own weight when > 0, else the product's (old PlacesOrders rule).
     */
    public function effectiveWeightGrams(): int
    {
        return ($this->weight_grams ?? 0) > 0 ? (int) $this->weight_grams : (int) ($this->product->weight_grams ?? 0);
    }

    /**
     * Precomputed fixed shipping for a country: variant map first (own weight), else the product's.
     */
    public function fixedShippingFor(?int $countryId): ?Money
    {
        if (($this->weight_grams ?? 0) > 0) {
            $minor = $countryId !== null ? ($this->fixed_shipping_costs[(string) $countryId] ?? null) : null;

            return $minor === null ? null : Money::ofMinor((int) $minor);
        }

        return $this->product->fixedShippingFor($countryId);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<AttributeOption, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(AttributeOption::class, 'product_variant_option');
    }
}
