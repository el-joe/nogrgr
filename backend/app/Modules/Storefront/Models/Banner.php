<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Database\Factories\BannerFactory;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Enums\BannerStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central (admin) default banner, ADM-085 / 085a: image (+ mobile image), translatable
 * title / subtitle / CTA, link, placement, sort, countries (none = all), all / selected
 * stores, optional schedule. Images on the central `public` disk (storefront/banners/…).
 *
 * @property int $id
 * @property BannerPlacement $placement
 * @property string|null $title
 * @property string|null $subtitle
 * @property string|null $cta_label
 * @property string $image_path
 * @property int|null $image_width
 * @property int|null $image_height
 * @property string|null $mobile_image_path
 * @property string|null $link_url
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property PromotionScope $store_scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Country> $countries
 * @property-read Collection<int, Tenant> $tenants
 */
class Banner extends Model implements AiTranslatable
{
    use CentralConnection;

    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'banners';

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'subtitle', 'cta_label'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'placement', 'title', 'subtitle', 'cta_label', 'image_path', 'image_width', 'image_height', 'mobile_image_path',
        'link_url', 'sort_order', 'is_active', 'starts_at', 'ends_at', 'store_scope',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => BannerPlacement::class,
            'image_width' => 'integer',
            'image_height' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'store_scope' => PromotionScope::class,
        ];
    }

    protected static function newFactory(): BannerFactory
    {
        return BannerFactory::new();
    }

    public function aiTranslatableFields(): array
    {
        return $this->translatable;
    }

    /**
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'banner_country');
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'banner_tenant');
    }

    public function status(): BannerStatus
    {
        return BannerStatus::derive($this->is_active, $this->starts_at, $this->ends_at);
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function mobileImageUrl(): ?string
    {
        return $this->mobile_image_path !== null ? Storage::disk('public')->url($this->mobile_image_path) : null;
    }
}
