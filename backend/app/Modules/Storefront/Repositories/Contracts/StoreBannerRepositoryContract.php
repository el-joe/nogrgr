<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\StoreBanner;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store-owned banners (tenant DB, VEN-035).
 *
 * @extends RepositoryContract<StoreBanner>
 */
interface StoreBannerRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, StoreBanner> countries eager loaded, by sort order
     */
    public function listing(?BannerPlacement $placement = null, bool $liveOnly = false): Collection;

    /**
     * @param  list<int>  $countryIds
     */
    public function syncCountries(StoreBanner $banner, array $countryIds): void;

    public function nextSortOrder(BannerPlacement $placement): int;

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids): void;

    public function count(): int;
}
