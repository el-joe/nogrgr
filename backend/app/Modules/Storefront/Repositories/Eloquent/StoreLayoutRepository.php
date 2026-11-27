<?php

namespace App\Modules\Storefront\Repositories\Eloquent;

use App\Modules\Storefront\Models\StoreLayout;
use App\Modules\Storefront\Models\StoreLayoutVersion;
use App\Modules\Storefront\Repositories\Contracts\StoreLayoutRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreLayout>
 */
class StoreLayoutRepository extends BaseRepository implements StoreLayoutRepositoryContract
{
    protected function model(): string
    {
        return StoreLayout::class;
    }

    public function findBySlot(string $slot): ?StoreLayout
    {
        return $this->query()->where('slot', $slot)->first();
    }

    public function versions(StoreLayout $layout, int $limit = 30): Collection
    {
        return StoreLayoutVersion::query()->where('store_layout_id', $layout->id)->orderByDesc('number')->limit($limit)->get();
    }

    public function findVersion(StoreLayout $layout, int $versionId): ?StoreLayoutVersion
    {
        return StoreLayoutVersion::query()->where('store_layout_id', $layout->id)->whereKey($versionId)->first();
    }

    public function addVersion(StoreLayout $layout, array $sections, ?int $staffId, ?string $staffName): StoreLayoutVersion
    {
        $number = (int) StoreLayoutVersion::query()->where('store_layout_id', $layout->id)->max('number') + 1;

        return StoreLayoutVersion::query()->create([
            'store_layout_id' => $layout->id,
            'number' => $number,
            'theme_id' => $layout->theme_id,
            'sections' => $sections,
            'published_by' => $staffId,
            'published_by_name' => $staffName,
        ]);
    }

    public function pruneVersions(StoreLayout $layout, int $keep): void
    {
        $keepIds = StoreLayoutVersion::query()->where('store_layout_id', $layout->id)->orderByDesc('number')->limit($keep)->pluck('id')->all();

        StoreLayoutVersion::query()->where('store_layout_id', $layout->id)
            ->whereNotIn('id', [...$keepIds, (int) $layout->published_version_id])
            ->delete();
    }
}
