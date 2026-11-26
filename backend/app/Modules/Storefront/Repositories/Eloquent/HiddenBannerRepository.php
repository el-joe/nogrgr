<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Models\HiddenCentralBanner;
use App\Modules\Storefront\Repositories\Contracts\HiddenBannerRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<HiddenCentralBanner>
 */
class HiddenBannerRepository extends BaseRepository implements HiddenBannerRepositoryContract
{
    protected function model(): string
    {
        return HiddenCentralBanner::class;
    }

    public function hiddenIds(): array
    {
        return HiddenCentralBanner::query()->pluck('banner_id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function hide(int $bannerId): void
    {
        HiddenCentralBanner::query()->firstOrCreate(['banner_id' => $bannerId]);
    }

    public function unhide(int $bannerId): void
    {
        HiddenCentralBanner::query()->where('banner_id', $bannerId)->delete();
    }
}
