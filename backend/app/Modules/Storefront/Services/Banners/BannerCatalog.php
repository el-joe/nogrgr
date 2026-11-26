<?php

namespace App\Modules\Storefront\Services\Banners;

use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Storefront\DTOs\BannerData;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Enums\ContentSource;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\HiddenBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreBannerRepositoryContract;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The ONE banner reader of a store (decision #65 b): the store's own banners (tenant DB)
 * merged with the central default banners that target the store (central DB), tagged
 * with `source` central | vendor. Nothing is copied between the databases.
 *
 * Rules: live only (active, inside the schedule); central banners for all stores or the
 * selected ones, minus those the store hid; country lists (none = everywhere) — a central
 * list is trimmed to the store's selling countries; ordered by sort order, the central
 * banner first on a tie. Tenant context required.
 */
class BannerCatalog
{
    public function __construct(
        private readonly BannerRepositoryContract $central,
        private readonly StoreBannerRepositoryContract $store,
        private readonly HiddenBannerRepositoryContract $hidden,
    ) {}

    /**
     * Storefront read: the live banners of a placement in a country (null = the visitor's
     * storefront country), texts in $locale (null = app locale). Empty = section hidden.
     *
     * @return list<BannerData>
     */
    public function forPlacement(string $placement, ?int $countryId = null, ?string $locale = null): array
    {
        $kind = BannerPlacement::tryFrom($placement);

        if ($kind === null) {
            return [];
        }

        $countryId ??= app(StorefrontContext::class)->countryId();

        return $this->merged($kind, $countryId, $locale ?? app()->getLocale(), liveOnly: true, includeHidden: false);
    }

    /**
     * Every banner of the store (vendor panel VEN-035 list): not only live ones, with the
     * hidden central ones flagged.
     *
     * @return list<BannerData>
     */
    public function all(?BannerPlacement $placement = null, ?int $countryId = null, ?string $locale = null): array
    {
        return $this->merged($placement, $countryId, $locale ?? app()->getLocale(), liveOnly: false, includeHidden: true);
    }

    /**
     * @return list<BannerData>
     */
    private function merged(?BannerPlacement $placement, ?int $countryId, string $locale, bool $liveOnly, bool $includeHidden): array
    {
        $tenant = StoreContext::tenant();
        $hidden = array_flip($this->hidden->hiddenIds());
        $rows = [];

        foreach ($this->central->applicableToTenant((int) $tenant->getTenantKey(), $placement, $liveOnly) as $banner) {
            $isHidden = isset($hidden[$banner->id]);
            $countries = StoreContext::trimCountries($tenant, $banner->countries->pluck('id')->map(static fn ($id): int => (int) $id)->all());

            if ($countries === null || ($isHidden && ! $includeHidden) || ! self::inCountry($countries, $countryId)) {
                continue;
            }

            $rows[] = [$banner->sort_order, 0, $banner->id, $this->toData($banner, ContentSource::Central, $locale, $isHidden)];
        }

        foreach ($this->store->listing($placement, $liveOnly) as $banner) {
            if (! self::inCountry($banner->countryIds(), $countryId)) {
                continue;
            }

            $rows[] = [$banner->sort_order, 1, $banner->id, $this->toData($banner, ContentSource::Vendor, $locale, false)];
        }

        usort($rows, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(static fn (array $row): BannerData => $row[3], $rows);
    }

    /**
     * @param  list<int>  $countries
     */
    private static function inCountry(array $countries, ?int $countryId): bool
    {
        return $countryId === null || $countries === [] || in_array($countryId, $countries, true);
    }

    private function toData(Banner|StoreBanner $banner, ContentSource $source, string $locale, bool $hidden): BannerData
    {
        $title = self::text($banner, 'title', $locale);

        return new BannerData(
            id: $banner->id,
            source: $source->value,
            placement: $banner->placement->value,
            title: $title,
            subtitle: self::text($banner, 'subtitle', $locale),
            imageUrl: $banner->imageUrl(),
            mobileImageUrl: $banner->mobileImageUrl(),
            url: $banner->link_url,
            ctaLabel: self::text($banner, 'cta_label', $locale),
            alt: $title,
            width: $banner->image_width,
            height: $banner->image_height,
            sortOrder: $banner->sort_order,
            hidden: $hidden,
        );
    }

    private static function text(Model $banner, string $field, string $locale): ?string
    {
        /** @var Banner|StoreBanner $banner */
        $value = $banner->getTranslation($field, $locale);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
