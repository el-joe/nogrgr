<?php

namespace App\Modules\Storefront\Services\Banners;

use App\Modules\Storefront\DTOs\BannerInputData;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Enums\ContentSource;
use App\Modules\Storefront\Events\BannersChanged;
use App\Modules\Storefront\Models\Banner;
use App\Modules\Storefront\Repositories\Contracts\BannerRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin › Content › Storefront › Default banners (ADM-085 / 085a): create, edit, switch
 * on / off, sort (drag), delete. Central DB; stores read them through BannerCatalog.
 */
class BannerService
{
    public function __construct(
        private readonly BannerRepositoryContract $banners,
        private readonly BannerImageStore $images,
    ) {}

    public function create(BannerInputData $data): Banner
    {
        if ($data->image === null) {
            throw ValidationException::withMessages(['image' => __('engagement::banners.validation.image_required')]);
        }

        $image = $this->images->store($data->image);
        $mobile = $data->mobileImage !== null ? $this->images->store($data->mobileImage) : null;

        $banner = DB::connection((new Banner)->getConnectionName())->transaction(function () use ($data, $image, $mobile): Banner {
            $banner = $this->banners->create($this->attributes($data) + [
                'image_path' => $image['path'],
                'image_width' => $image['width'],
                'image_height' => $image['height'],
                'mobile_image_path' => $mobile['path'] ?? null,
                'sort_order' => $this->banners->nextSortOrder($data->placement),
            ]);
            $this->banners->syncTargets($banner, $data->countryIds, $data->tenantIds);

            return $banner;
        });

        BannersChanged::dispatch(ContentSource::Central, $banner->id);

        return $banner;
    }

    public function update(Banner $banner, BannerInputData $data): Banner
    {
        $old = [$banner->image_path, $banner->mobile_image_path];
        $attributes = $this->attributes($data);

        if ($data->placement !== $banner->placement) {
            $attributes['sort_order'] = $this->banners->nextSortOrder($data->placement);
        }

        if ($data->image !== null) {
            $image = $this->images->store($data->image);
            $attributes += ['image_path' => $image['path'], 'image_width' => $image['width'], 'image_height' => $image['height']];
        }

        if ($data->mobileImage !== null) {
            $attributes['mobile_image_path'] = $this->images->store($data->mobileImage)['path'];
        } elseif ($data->removeMobileImage) {
            $attributes['mobile_image_path'] = null;
        }

        DB::connection($banner->getConnectionName())->transaction(function () use ($banner, $attributes, $data): void {
            $this->banners->update($banner, $attributes);
            $this->banners->syncTargets($banner, $data->countryIds, $data->tenantIds);
        });

        $this->images->delete(...array_diff(array_filter($old), [$banner->image_path, $banner->mobile_image_path]));
        BannersChanged::dispatch(ContentSource::Central, $banner->id);

        return $banner;
    }

    public function setActive(Banner $banner, bool $active): void
    {
        $this->banners->update($banner, ['is_active' => $active]);
        BannersChanged::dispatch(ContentSource::Central, $banner->id);
    }

    /**
     * @param  list<int>  $ids  the new order of the banners shown in one list
     */
    public function reorder(array $ids): void
    {
        $this->banners->reorder($ids);
        BannersChanged::dispatch(ContentSource::Central);
    }

    public function delete(Banner $banner): void
    {
        $paths = [$banner->image_path, $banner->mobile_image_path];
        $this->banners->delete($banner);
        $this->images->delete(...$paths);
        BannersChanged::dispatch(ContentSource::Central, $banner->id);
    }

    /**
     * @return array<int, int> country id => banner count (0 = all-country banners)
     */
    public function countryCounts(): array
    {
        return $this->banners->countsByCountry();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(BannerInputData $data): array
    {
        return [
            'placement' => $data->placement,
            'title' => $data->title,
            'subtitle' => $data->subtitle,
            'cta_label' => $data->ctaLabel,
            'link_url' => $data->linkUrl,
            'is_active' => $data->isActive,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'store_scope' => $data->storeScope,
        ];
    }

    public static function placementOrDefault(?string $value): BannerPlacement
    {
        return BannerPlacement::tryFrom((string) $value) ?? BannerPlacement::HomeHero;
    }
}
