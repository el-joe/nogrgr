<?php

namespace App\Modules\Ai\Repositories\Eloquent;

use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Models\VideoAd;
use App\Modules\Ai\Repositories\Contracts\VideoAdRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<VideoAd>
 */
class VideoAdRepository extends BaseRepository implements VideoAdRepositoryContract
{
    protected function model(): string
    {
        return VideoAd::class;
    }

    public function activeCount(): int
    {
        return $this->query()
            ->whereIn('status', array_map(static fn (VideoAdStatus $status): string => $status->value, VideoAdStatus::active()))
            ->where('created_at', '>=', now()->subHours(2))
            ->count();
    }

    public function latestReady(int $limit, ?int $countryId = null): Collection
    {
        return $this->query()
            ->where('status', VideoAdStatus::Ready->value)
            ->when($countryId !== null, static fn (Builder $query) => $query->where(static fn (Builder $where) => $where->where('country_id', $countryId)->orWhereNull('country_id')))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function listing(): Builder
    {
        return $this->query()->select('video_ads.*');
    }
}
