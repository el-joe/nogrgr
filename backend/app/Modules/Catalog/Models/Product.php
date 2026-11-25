<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central catalog product (central DB). Money in USD minor units.
 *
 * @property int $id
 * @property string $slug
 * @property string $sku
 * @property string $name
 * @property string|null $label
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property ProductStatus $status
 * @property DeliveryScope $delivery_scope
 * @property AssignmentScope $assignment_scope
 * @property Money $base_price
 * @property Money|null $sale_price
 * @property Money|null $cost_price
 * @property int $stock
 * @property int $min_stock
 * @property bool $manage_stock
 * @property bool $is_taxable
 * @property bool $requires_shipping
 * @property int|null $weight_grams
 * @property array<string, int>|null $fixed_shipping_costs
 * @property list<int>|null $allowed_country_ids
 * @property string|null $factory
 * @property int $sold_count
 * @property int $sort_order
 * @property Carbon|null $published_at
 * @property Carbon|null $out_of_stock_at
 * @property string|null $external_source
 * @property int|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Product extends Model
{
    use CentralConnection;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'label', 'summary', 'description', 'meta_title', 'meta_keywords', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug', 'sku', 'name', 'label', 'summary', 'description', 'meta_title', 'meta_keywords', 'meta_description',
        'status', 'delivery_scope', 'assignment_scope', 'base_price', 'sale_price', 'cost_price',
        'stock', 'min_stock', 'manage_stock', 'is_taxable', 'requires_shipping', 'weight_grams',
        'fixed_shipping_costs', 'allowed_country_ids', 'factory', 'sold_count', 'sort_order',
        'published_at', 'out_of_stock_at', 'external_source', 'external_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'delivery_scope' => DeliveryScope::class,
            'assignment_scope' => AssignmentScope::class,
            'base_price' => MoneyCast::class.':USD',
            'sale_price' => MoneyCast::class.':USD',
            'cost_price' => MoneyCast::class.':USD',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'manage_stock' => 'boolean',
            'is_taxable' => 'boolean',
            'requires_shipping' => 'boolean',
            'weight_grams' => 'integer',
            'fixed_shipping_costs' => 'array',
            'allowed_country_ids' => 'array',
            'sold_count' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
            'out_of_stock_at' => 'datetime',
            'external_id' => 'integer',
        ];
    }

    /**
     * Tenant cost basis (old TenantPricingService::centralBasePrice): sale price when > 0, else base price.
     */
    public function costBasis(): Money
    {
        return $this->sale_price !== null && $this->sale_price->isPositive() ? $this->sale_price : $this->base_price;
    }

    /**
     * Precomputed fixed shipping for a country (null when none is configured).
     */
    public function fixedShippingFor(?int $countryId): ?Money
    {
        $minor = $countryId !== null ? ($this->fixed_shipping_costs[(string) $countryId] ?? null) : null;

        return $minor === null ? null : Money::ofMinor((int) $minor);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('sort_order');
    }

    /**
     * @return BelongsToMany<Attribute, $this>
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute');
    }

    /**
     * @return BelongsToMany<ProductLabel, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ProductLabel::class, 'product_label_product')->orderBy('product_labels.sort_order');
    }

    /**
     * @return BelongsToMany<ProductBadge, $this>
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(ProductBadge::class, 'product_badge_product')->withPivot(['country_id', 'sort_order']);
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function assignedTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'product_tenant_assignments')->withTimestamps();
    }

    /**
     * @return MorphMany<CatalogMedia, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(CatalogMedia::class, 'mediable')->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }

    /**
     * Products a tenant may carry: scope "all", or "selected" with an assignment row.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function offeredTo(Builder $query, int $tenantId): void
    {
        $query->where(function (Builder $inner) use ($tenantId): void {
            $inner->where('assignment_scope', AssignmentScope::All)
                ->orWhereExists(function ($exists) use ($tenantId): void {
                    $exists->selectRaw('1')->from('product_tenant_assignments')
                        ->whereColumn('product_tenant_assignments.product_id', 'products.id')
                        ->where('product_tenant_assignments.tenant_id', $tenantId);
                });
        });
    }
}
