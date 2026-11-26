<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Database\Factories\StoreCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

/**
 * A category the store created itself (tenant DB, decision #74). Central categories are
 * never copied here; see StoreCentralCategory for the store's settings of those.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property string|null $image_path
 * @property list<int>|null $country_ids null = every selling country
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StoreCategory|null $parent
 */
class StoreCategory extends Model implements AiTranslatable
{
    /** @use HasFactory<StoreCategoryFactory> */
    use HasFactory;

    use HasTranslations;

    public const IMAGE_DISK = 'public';

    public const IMAGE_DIRECTORY = 'store-categories';

    protected $table = 'store_categories';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description', 'meta_keywords', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'meta_keywords', 'meta_description', 'image_path', 'country_ids', 'is_active', 'is_featured', 'sort_order'];

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    protected static function newFactory(): StoreCategoryFactory
    {
        return StoreCategoryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'country_ids' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StoreCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<StoreCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path !== null ? Storage::disk(self::IMAGE_DISK)->url($this->image_path) : null;
    }
}
