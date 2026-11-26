<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Enums\BannerStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

/**
 * A store's own banner (tenant DB, VEN-035): same shape as the central Banner, countries
 * in store_banner_countries (none = every selling country). Images on the store's
 * tenant-suffixed `public` disk, served from /tenancy/assets/.
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, StoreBannerCountry> $countryRows
 */
class StoreBanner extends Model
{
    use HasTranslations;

    protected $table = 'store_banners';

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'subtitle', 'cta_label'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'placement', 'title', 'subtitle', 'cta_label', 'image_path', 'image_width', 'image_height', 'mobile_image_path',
        'link_url', 'sort_order', 'is_active', 'starts_at', 'ends_at',
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
        ];
    }

    /**
     * @return HasMany<StoreBannerCountry, $this>
     */
    public function countryRows(): HasMany
    {
        return $this->hasMany(StoreBannerCountry::class);
    }

    /**
     * @return list<int>
     */
    public function countryIds(): array
    {
        return $this->countryRows->pluck('country_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
    }

    public function status(): BannerStatus
    {
        return BannerStatus::derive($this->is_active, $this->starts_at, $this->ends_at);
    }

    public function imageUrl(): string
    {
        return self::assetUrl($this->image_path);
    }

    public function mobileImageUrl(): ?string
    {
        return $this->mobile_image_path !== null ? self::assetUrl($this->mobile_image_path) : null;
    }

    private static function assetUrl(string $path): string
    {
        $tenant = tenant();

        return $tenant instanceof Tenant
            ? StoreSettingsService::storeUrl($tenant).'/tenancy/assets/'.$path
            : Storage::disk('public')->url($path);
    }
}
