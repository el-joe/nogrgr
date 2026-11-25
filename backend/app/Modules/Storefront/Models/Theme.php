<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Storefront\Enums\ThemeFamily;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Storefront theme (central catalogue). The preview image is an admin upload
 * (`preview_path` on the public disk, P7) or the bundled Figma export
 * resources/images/central/themes/{slug}.webp.
 *
 * @property int $id
 * @property string $slug
 * @property ThemeFamily $family
 * @property int $variant
 * @property string $name
 * @property string|null $description
 * @property string|null $preview_path
 * @property bool $is_active
 * @property int $sort_order
 * @property-read string|null $preview_url
 * @property-read Collection<int, ThemeCategory> $categories
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Theme extends Model
{
    use CentralConnection;
    use HasTranslations;

    protected $table = 'themes';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description'];

    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'family', 'variant', 'name', 'description', 'preview_path', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'family' => ThemeFamily::class,
            'variant' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<ThemeCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ThemeCategory::class, 'theme_category_theme')->orderBy('sort_order');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->preview_path !== null) {
                return Storage::disk('public')->url($this->preview_path);
            }

            $bundled = 'resources/images/central/themes/'.$this->slug.'.webp';

            return is_file(base_path($bundled)) ? Vite::asset($bundled) : null;
        });
    }
}
