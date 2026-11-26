<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Database\Factories\StoreProductFactory;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A product the store sells (tenant DB, decision #65): an imported central product
 * (only `central_product_id` + store fields; content is read from central ⊕ overrides by
 * StoreCatalogReader, never copied) or a vendor-own product (content in the own-only
 * columns). `markup` = per-country profit rows {"default"|country_id: {profit_type,
 * profit_value}}, `variant_markup` = the same per central variant id. Own prices in USD minor units.
 *
 * @property int $id
 * @property StoreProductSource $source
 * @property int|null $central_product_id
 * @property string|null $name
 * @property string|null $label
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $slug
 * @property string|null $sku
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property array<int|string, array<string, mixed>>|null $markup
 * @property array<int|string, array<int|string, array<string, mixed>>>|null $variant_markup central variant id => country rows
 * @property list<int>|null $category_ids store placement (central category ids; null = central categories)
 * @property list<int>|null $badge_ids store placement (central badge ids; null = central badges)
 * @property list<int>|null $store_category_ids placement in store-own categories (decision #74)
 * @property Money|null $price
 * @property Money|null $sale_price
 * @property Money|null $cost_price
 * @property int|null $stock
 * @property int $min_stock
 * @property bool $manage_stock
 * @property bool $is_taxable
 * @property int|null $weight_grams
 * @property list<int>|null $country_ids
 * @property int $sold_count
 * @property Carbon|null $imported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $centralProduct
 * @property-read ProductOverride|null $override
 */
class StoreProduct extends Model implements AiTranslatable
{
    /** @use HasFactory<StoreProductFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'store_products';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'label', 'summary', 'description', 'meta_title', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source', 'central_product_id', 'name', 'label', 'summary', 'description', 'meta_title', 'meta_description',
        'slug', 'sku', 'is_active', 'is_featured', 'sort_order', 'markup', 'variant_markup', 'category_ids', 'store_category_ids', 'badge_ids', 'price', 'sale_price', 'cost_price',
        'stock', 'min_stock', 'manage_stock', 'is_taxable', 'weight_grams', 'country_ids', 'sold_count', 'imported_at',
    ];

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    protected static function newFactory(): StoreProductFactory
    {
        return StoreProductFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => StoreProductSource::class,
            'central_product_id' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'markup' => 'array',
            'variant_markup' => 'array',
            'category_ids' => 'array',
            'store_category_ids' => 'array',
            'badge_ids' => 'array',
            'price' => MoneyCast::class.':USD',
            'sale_price' => MoneyCast::class.':USD',
            'cost_price' => MoneyCast::class.':USD',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'manage_stock' => 'boolean',
            'is_taxable' => 'boolean',
            'weight_grams' => 'integer',
            'country_ids' => 'array',
            'sold_count' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function isOwn(): bool
    {
        return $this->source === StoreProductSource::Own;
    }

    /**
     * Central catalog product (central DB; eager loading runs on the central connection).
     *
     * @return BelongsTo<Product, $this>
     */
    public function centralProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'central_product_id');
    }

    /**
     * Store overrides of the central product (tenant DB, keyed by the central id).
     *
     * @return HasOne<ProductOverride, $this>
     */
    public function override(): HasOne
    {
        return $this->hasOne(ProductOverride::class, 'product_id', 'central_product_id');
    }

    /**
     * @return HasMany<VariantOverride, $this>
     */
    public function variantOverrides(): HasMany
    {
        return $this->hasMany(VariantOverride::class, 'product_id', 'central_product_id');
    }

    /**
     * Variants of an OWN product (central variants are read from the central DB).
     *
     * @return HasMany<StoreProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(StoreProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<StoreProductMarket, $this>
     */
    public function markets(): HasMany
    {
        return $this->hasMany(StoreProductMarket::class);
    }

    /**
     * Gallery of an own product (tenant `catalog_media`, tenant-suffixed public disk).
     *
     * @return MorphMany<CatalogMedia, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(CatalogMedia::class, 'mediable')->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Own product sell price (ORDER_FORMULAS §1 "own products"): sale price when > 0, else base price.
     */
    public function ownSellPrice(): Money
    {
        if ($this->sale_price !== null && $this->sale_price->isPositive()) {
            return $this->sale_price;
        }

        return $this->price ?? Money::zero();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function central(Builder $query): void
    {
        $query->where('source', StoreProductSource::Central);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function own(Builder $query): void
    {
        $query->where('source', StoreProductSource::Own);
    }
}
