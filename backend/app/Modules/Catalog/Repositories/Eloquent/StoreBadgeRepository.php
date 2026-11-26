<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\StoreBadgePosition;
use App\Modules\Catalog\Repositories\Contracts\StoreBadgeRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<StoreBadgePosition>
 */
class StoreBadgeRepository extends BaseRepository implements StoreBadgeRepositoryContract
{
    protected function model(): string
    {
        return StoreBadgePosition::class;
    }

    public function positions(int $badgeId): array
    {
        return $this->query()
            ->where('badge_id', $badgeId)
            ->pluck('position', 'store_product_id')
            ->map(static fn (mixed $position): int => (int) $position)
            ->all();
    }

    public function savePositions(int $badgeId, array $storeProductIds): void
    {
        $this->query()->where('badge_id', $badgeId)->delete();
        $now = now();
        $rows = [];

        foreach (array_values(array_unique($storeProductIds)) as $position => $id) {
            $rows[] = ['badge_id' => $badgeId, 'store_product_id' => $id, 'position' => $position + 1, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->query()->insert($chunk);
        }
    }

    public function append(int $badgeId, array $storeProductIds): void
    {
        $known = $this->positions($badgeId);
        $next = $known === [] ? 1 : max($known) + 1;
        $now = now();
        $rows = [];

        foreach (array_values(array_unique($storeProductIds)) as $id) {
            if (! isset($known[$id])) {
                $rows[] = ['badge_id' => $badgeId, 'store_product_id' => $id, 'position' => $next++, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->query()->insert($chunk);
        }
    }

    public function forget(int $badgeId, int $storeProductId): void
    {
        $this->query()->where('badge_id', $badgeId)->where('store_product_id', $storeProductId)->delete();
    }
}
