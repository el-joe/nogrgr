<?php

namespace App\Modules\Storefront\Services\Banners;

use App\Modules\Commerce\Services\Promotions\StoreContext;
use App\Modules\Storefront\DTOs\BannerInputData;
use App\Modules\Storefront\Enums\ContentSource;
use App\Modules\Storefront\Events\BannersChanged;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Repositories\Contracts\HiddenBannerRepositoryContract;
use App\Modules\Storefront\Repositories\Contracts\StoreBannerRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Store banners service API (VEN-035 / 035a, page built in P8): own banners CRUD + sort,
 * and hide / show a central banner. Runs in the store's tenant context.
 */
class StoreBannerService
{
    public function __construct(
        private readonly StoreBannerRepositoryContract $banners,
        private readonly HiddenBannerRepositoryContract $hidden,
        private readonly BannerImageStore $images,
    ) {}

    public function create(BannerInputData $data): StoreBanner
    {
        $tenantId = $this->tenantId();

        if ($data->image === null) {
            throw ValidationException::withMessages(['image' => __('engagement::banners.validation.image_required')]);
        }

        $image = $this->images->store($data->image);
        $mobile = $data->mobileImage !== null ? $this->images->store($data->mobileImage) : null;

        $banner = DB::transaction(function () use ($data, $image, $mobile): StoreBanner {
            $banner = $this->banners->create($this->attributes($data) + [
                'image_path' => $image['path'],
                'image_width' => $image['width'],
                'image_height' => $image['height'],
                'mobile_image_path' => $mobile['path'] ?? null,
                'sort_order' => $this->banners->nextSortOrder($data->placement),
            ]);
            $this->banners->syncCountries($banner, $data->countryIds);

            return $banner;
        });

        BannersChanged::dispatch(ContentSource::Vendor, $banner->id, $tenantId);

        return $banner;
    }

    public function update(StoreBanner $banner, BannerInputData $data): StoreBanner
    {
        $tenantId = $this->tenantId();
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

        DB::transaction(function () use ($banner, $attributes, $data): void {
            $this->banners->update($banner, $attributes);
            $this->banners->syncCountries($banner, $data->countryIds);
        });

        $this->images->delete(...array_diff(array_filter($old), [$banner->image_path, $banner->mobile_image_path]));
        BannersChanged::dispatch(ContentSource::Vendor, $banner->id, $tenantId);

        return $banner;
    }

    public function setActive(StoreBanner $banner, bool $active): void
    {
        $this->banners->update($banner, ['is_active' => $active]);
        BannersChanged::dispatch(ContentSource::Vendor, $banner->id, $this->tenantId());
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids): void
    {
        $this->banners->reorder($ids);
        BannersChanged::dispatch(ContentSource::Vendor, null, $this->tenantId());
    }

    public function delete(StoreBanner $banner): void
    {
        $tenantId = $this->tenantId();
        $paths = [$banner->image_path, $banner->mobile_image_path];
        $this->banners->delete($banner);
        $this->images->delete(...$paths);
        BannersChanged::dispatch(ContentSource::Vendor, $banner->id, $tenantId);
    }

    /**
     * Hide / show a central banner on this store only (the central row is untouched).
     */
    public function setCentralHidden(int $centralBannerId, bool $hidden): void
    {
        $tenantId = $this->tenantId();
        $hidden ? $this->hidden->hide($centralBannerId) : $this->hidden->unhide($centralBannerId);
        BannersChanged::dispatch(ContentSource::Central, $centralBannerId, $tenantId);
    }

    public function count(): int
    {
        return $this->banners->count();
    }

    private function tenantId(): int
    {
        return (int) StoreContext::tenant()->getTenantKey();
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
        ];
    }
}
