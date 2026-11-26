<?php

namespace App\Modules\Storefront\Repositories\Contracts;

use App\Modules\Storefront\Models\HiddenCentralBanner;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Central banners hidden by the current store (tenant DB).
 *
 * @extends RepositoryContract<HiddenCentralBanner>
 */
interface HiddenBannerRepositoryContract extends RepositoryContract
{
    /**
     * @return list<int>
     */
    public function hiddenIds(): array;

    public function hide(int $bannerId): void;

    public function unhide(int $bannerId): void;
}
