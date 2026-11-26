<?php

namespace App\Modules\Ai\Repositories\Contracts;

use App\Modules\Ai\Models\VideoAd;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Video ads of the AI Advertising Studio (tenant DB, current store).
 *
 * @extends RepositoryContract<VideoAd>
 */
interface VideoAdRepositoryContract extends RepositoryContract
{
    /**
     * Ads still being generated (count toward the monthly quota before their usage row exists).
     */
    public function activeCount(): int;

    /**
     * Newest ready ads, optionally of one target country.
     *
     * @return Collection<int, VideoAd>
     */
    public function latestReady(int $limit, ?int $countryId = null): Collection;

    /**
     * Listing of the Advertising Library (DataTable).
     *
     * @return Builder<VideoAd>
     */
    public function listing(): Builder;
}
