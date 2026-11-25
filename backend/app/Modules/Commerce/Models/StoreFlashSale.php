<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Commerce\Database\Factories\StoreFlashSaleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

/**
 * Store-owned flash sale (tenant DB, VEN-041): vendor-funded (source = vendor). Products
 * are typed pairs (central product id | own store product id); countries are central ids.
 *
 * @property int $id
 * @property string $title
 * @property float $discount_percentage
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_active
 * @property string|null $banner_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, StoreFlashSaleProduct> $productRows
 * @property-read Collection<int, StoreFlashSaleCountry> $countryRows
 */
class StoreFlashSale extends Model implements AiTranslatable
{
    /** @use HasFactory<StoreFlashSaleFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    protected $table = 'store_flash_sales';

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'discount_percentage', 'starts_at', 'ends_at', 'is_active', 'banner_path'];

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
        ];
    }

    protected static function newFactory(): StoreFlashSaleFactory
    {
        return StoreFlashSaleFactory::new();
    }

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @return HasMany<StoreFlashSaleProduct, $this>
     */
    public function productRows(): HasMany
    {
        return $this->hasMany(StoreFlashSaleProduct::class);
    }

    /**
     * @return HasMany<StoreFlashSaleCountry, $this>
     */
    public function countryRows(): HasMany
    {
        return $this->hasMany(StoreFlashSaleCountry::class);
    }

    /**
     * Banner on the store's (tenant-suffixed) public disk.
     */
    public function bannerUrl(): ?string
    {
        return $this->banner_path !== null ? Storage::disk('public')->url($this->banner_path) : null;
    }
}
