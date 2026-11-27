<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\Enums\ThemeFamily;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Storefront theme (central catalogue, decision #119: 13 themes shown as separate
 * themes; family + variant stay internal). Platform default (`is_default`, one active
 * theme), allowed countries (`theme_country`, empty = all), home section defaults
 * (`home_sections`, ADM-087) and template parts (ADM-088). The preview image is an admin upload
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
 * @property bool $is_default
 * @property list<array{key: string, enabled: bool}>|null $home_sections
 * @property int $sort_order
 * @property-read Collection<int, Country> $countries
 * @property-read Collection<int, ThemePart> $parts
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
    protected $fillable = ['slug', 'family', 'variant', 'name', 'description', 'preview_path', 'home_sections', 'is_active', 'is_default', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'family' => ThemeFamily::class,
            'variant' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'home_sections' => 'array',
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
     * Countries the theme may be used in (empty = every country).
     *
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'theme_country');
    }

    /**
     * @return HasMany<ThemePart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ThemePart::class)->orderBy('type')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Allowed in a country: no country list (= everywhere) or the country is on it.
     * Uses the loaded `countries` relation when present.
     */
    public function allowsCountry(?int $countryId): bool
    {
        if ($countryId === null) {
            return true;
        }

        $ids = $this->relationLoaded('countries') ? $this->countries->modelKeys() : $this->countries()->pluck('countries.id')->all();

        return $ids === [] || in_array($countryId, array_map('intval', $ids), true);
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
