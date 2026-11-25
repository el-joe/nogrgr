<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Database\Factories\FlashSaleFactory;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central (admin) flash sale, ADM-026: % off the selected central products between two
 * dates, in all or the selected countries and stores. Platform-funded (source = central).
 *
 * @property int $id
 * @property string $title
 * @property float $discount_percentage
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_active
 * @property string|null $banner_path
 * @property PromotionScope $store_scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, Country> $countries
 * @property-read Collection<int, Tenant> $tenants
 * @property-read int|null $products_count
 */
class FlashSale extends Model implements AiTranslatable
{
    use CentralConnection;

    /** @use HasFactory<FlashSaleFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    protected $table = 'flash_sales';

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'discount_percentage', 'starts_at', 'ends_at', 'is_active', 'banner_path', 'store_scope'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percentage' => 'float',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'store_scope' => PromotionScope::class,
        ];
    }

    protected static function newFactory(): FlashSaleFactory
    {
        return FlashSaleFactory::new();
    }

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'flash_sale_product');
    }

    /**
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'flash_sale_country');
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'flash_sale_tenant');
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path !== null ? Storage::disk('public')->url($this->banner_path) : null;
    }
}
