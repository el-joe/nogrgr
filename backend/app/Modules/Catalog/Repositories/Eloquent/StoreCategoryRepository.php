<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreCategoryPosition;
use App\Modules\Catalog\Models\StoreCentralCategory;
use App\Modules\Catalog\Repositories\Contracts\StoreCategoryRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StoreCategory>
 */
class StoreCategoryRepository extends BaseRepository implements StoreCategoryRepositoryContract
{
    protected function model(): string
    {
        return StoreCategory::class;
    }

    public function allOrdered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->query()->where('slug', $slug)->when($exceptId !== null, static fn ($query) => $query->whereKeyNot($exceptId))->exists();
    }

    public function nextSortOrder(): int
    {
        $own = (int) $this->query()->max('sort_order');
        $central = (int) StoreCentralCategory::query()->max('sort_order');

        return max($own, $central) + 1;
    }

    public function descendantIds(int $id): array
    {
        $children = [];

        foreach ($this->query()->whereNotNull('parent_id')->get(['id', 'parent_id']) as $row) {
            $children[(int) $row->parent_id][] = (int) $row->id;
        }

        $result = [];
        $stack = $children[$id] ?? [];

        while ($stack !== []) {
            $next = array_pop($stack);

            if (in_array($next, $result, true)) {
                continue;
            }

            $result[] = $next;
            array_push($stack, ...($children[$next] ?? []));
        }

        return $result;
    }

    public function centralSettings(): array
    {
        return StoreCentralCategory::query()->get()->keyBy('central_category_id')->all();
    }

    public function saveCentralSetting(int $centralCategoryId, array $attributes): StoreCentralCategory
    {
        return StoreCentralCategory::query()->updateOrCreate(['central_category_id' => $centralCategoryId], $attributes);
    }

    public function positions(string $categoryRef): array
    {
        return StoreCategoryPosition::query()
            ->where('category_ref', $categoryRef)
            ->pluck('position', 'store_product_id')
            ->map(static fn (mixed $position): int => (int) $position)
            ->all();
    }

    public function savePositions(string $categoryRef, array $storeProductIds): void
    {
        StoreCategoryPosition::query()->where('category_ref', $categoryRef)->delete();
        $now = now();
        $rows = [];

        foreach (array_values(array_unique($storeProductIds)) as $position => $id) {
            $rows[] = ['category_ref' => $categoryRef, 'store_product_id' => $id, 'position' => $position + 1, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StoreCategoryPosition::query()->insert($chunk);
        }
    }

    public function forgetPosition(string $categoryRef, ?int $storeProductId = null): void
    {
        StoreCategoryPosition::query()
            ->where('category_ref', $categoryRef)
            ->when($storeProductId !== null, static fn ($query) => $query->where('store_product_id', $storeProductId))
            ->delete();
    }
}
