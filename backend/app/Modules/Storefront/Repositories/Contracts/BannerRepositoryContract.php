<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Models\Banner;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central (admin) banners, ADM-085.
 *
 * @extends RepositoryContract<Banner>
 */
interface BannerRepositoryContract extends RepositoryContract
{
    /**
     * @param  list<int>  $countryIds
     * @param  list<int>  $tenantIds
     */
    public function syncTargets(Banner $banner, array $countryIds, array $tenantIds): void;

    /**
     * Central banners a store may show (all-stores or selected for it), countries eager
     * loaded, ordered by placement sort. Live-only = active and inside its schedule.
     *
     * @return Collection<int, Banner>
     */
    public function applicableToTenant(int $tenantId, ?BannerPlacement $placement = null, bool $liveOnly = false): Collection;

    public function nextSortOrder(BannerPlacement $placement): int;

    /**
     * Puts the given banners in this order, reusing their current sort slots (banners not
     * in the list keep their place). Unknown ids = no change.
     *
     * @param  list<int>  $ids
     */
    public function reorder(array $ids): void;

    /**
     * Banner count per country id (0 = banners without countries, shown everywhere).
     *
     * @return array<int, int>
     */
    public function countsByCountry(): array;
}
