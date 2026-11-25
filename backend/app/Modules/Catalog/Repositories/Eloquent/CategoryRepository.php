<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Category>
 */
class CategoryRepository extends BaseRepository implements CategoryRepositoryContract
{
    protected function model(): string
    {
        return Category::class;
    }

    public function allForTree(): Collection
    {
        return $this->query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function parentMap(): array
    {
        /** @var array<int, int|null> $map */
        $map = $this->query()->pluck('parent_id', 'id')->map(static fn (mixed $parent): ?int => $parent === null ? null : (int) $parent)->all();

        return $map;
    }

    public function savePositions(array $positions): void
    {
        foreach ($positions as $id => $position) {
            $this->query()->whereKey($id)->update([
                'parent_id' => $position['parent_id'],
                'sort_order' => $position['sort_order'],
            ]);
        }
    }

    public function nextSortOrder(?int $parentId): int
    {
        $max = $this->query()->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : (int) $max + 1;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function hasChildren(Category $category): bool
    {
        return $this->query()->where('parent_id', $category->id)->exists();
    }

    public function productsInOrder(Category $category): Collection
    {
        /** @var Collection<int, Product> $products */
        $products = $category->products()->with('media')->orderBy('products.id')->get();

        return $products;
    }

    public function productIds(Category $category): array
    {
        return array_map('intval', $category->products()->pluck('products.id')->all());
    }

    public function saveProductOrder(Category $category, array $productIds): void
    {
        foreach (array_values($productIds) as $position => $productId) {
            $category->products()->updateExistingPivot($productId, ['sort_order' => $position]);
        }
    }
}
