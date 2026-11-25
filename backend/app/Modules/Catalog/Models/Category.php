<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central catalog category (tree).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property RecordStatus $status
 * @property bool $is_featured
 * @property bool $show_in_menu
 * @property int $sort_order
 * @property string|null $thumbnail_path
 * @property string|null $thumbnail_webp_path
 * @property string|null $thumbnail_avif_path
 * @property string|null $external_source
 * @property int|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Category extends Model
{
    use CentralConnection;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    public const THUMBNAIL_DISK = 'public';

    public const THUMBNAIL_DIRECTORY = 'catalog/categories';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = ['parent_id', 'slug', 'name', 'description', 'meta_title', 'meta_description', 'status', 'is_featured', 'show_in_menu', 'sort_order', 'thumbnail_path', 'thumbnail_webp_path', 'thumbnail_avif_path', 'external_source', 'external_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'is_featured' => 'boolean',
            'show_in_menu' => 'boolean',
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'external_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /**
     * Best available thumbnail URL (original upload; the <picture> sources use the derivatives).
     */
    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path !== null ? Storage::disk(self::THUMBNAIL_DISK)->url($this->thumbnail_path) : null;
    }

    public function thumbnailWebpUrl(): ?string
    {
        return $this->thumbnail_webp_path !== null ? Storage::disk(self::THUMBNAIL_DISK)->url($this->thumbnail_webp_path) : null;
    }

    public function thumbnailAvifUrl(): ?string
    {
        return $this->thumbnail_avif_path !== null ? Storage::disk(self::THUMBNAIL_DISK)->url($this->thumbnail_avif_path) : null;
    }

    /**
     * Locales that have a name, e.g. ["en", "ar"].
     *
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        return array_values(array_keys(array_filter($this->getTranslations('name'), static fn (mixed $value): bool => is_string($value) && $value !== '')));
    }
}
