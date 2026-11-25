<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\DTOs\CategoryData;
use App\Modules\Catalog\Jobs\ConvertCategoryThumbnail;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central category tree (ADM-014 / 014a / 014b / 014c): CRUD with thumbnail, featured
 * switch, drag-and-drop re-ordering / re-nesting of the whole tree (cycle-free by
 * construction and checked on every re-parent) and the product order inside a category.
 */
class CategoryService extends BaseService
{
    /**
     * Deepest level a category may sit at (1 = root).
     */
    public const MAX_DEPTH = 5;

    public function __construct(
        private readonly CategoryRepositoryContract $categories,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * Nested tree: list of ['category' => Category, 'depth' => int, 'children' => list<…>].
     *
     * @return list<array{category: Category, depth: int, children: list<array<string, mixed>>}>
     */
    public function tree(): array
    {
        $byParent = [];

        foreach ($this->categories->allForTree() as $category) {
            $byParent[$category->parent_id ?? 0][] = $category;
        }

        $build = function (int $parentId, int $depth) use (&$build, $byParent): array {
            $nodes = [];

            foreach ($byParent[$parentId] ?? [] as $category) {
                $nodes[] = ['category' => $category, 'depth' => $depth, 'children' => $build($category->id, $depth + 1)];
            }

            return $nodes;
        };

        return $build(0, 1);
    }

    /**
     * Tree flattened in display order: id => ['category' => Category, 'depth' => int, 'path' => string].
     *
     * @return array<int, array{category: Category, depth: int, path: string}>
     */
    public function flatten(): array
    {
        $flat = [];
        $walk = function (array $nodes, string $prefix) use (&$walk, &$flat): void {
            foreach ($nodes as $node) {
                $path = $prefix === '' ? (string) $node['category']->name : $prefix.' › '.$node['category']->name;
                $flat[$node['category']->id] = ['category' => $node['category'], 'depth' => $node['depth'], 'path' => $path];
                $walk($node['children'], $path);
            }
        };
        $walk($this->tree(), '');

        return $flat;
    }

    /**
     * Parent choices for the form: every category except the edited one and its descendants.
     *
     * @return array<int, string>
     */
    public function parentOptions(?Category $editing = null): array
    {
        $excluded = $editing !== null ? [$editing->id, ...$this->descendantIds($editing)] : [];
        $options = [];

        foreach ($this->flatten() as $id => $row) {
            if (! in_array($id, $excluded, true) && $row['depth'] < self::MAX_DEPTH) {
                $options[$id] = $row['path'];
            }
        }

        return $options;
    }

    /**
     * Category choices with product counts (badge "whole category" picker).
     *
     * @return array<int, string>
     */
    public function optionsWithCounts(): array
    {
        $options = [];

        foreach ($this->flatten() as $id => $row) {
            $options[$id] = $row['path'].' ('.number_format((int) $row['category']->products_count).')';
        }

        return $options;
    }

    public function count(): int
    {
        return $this->categories->query()->count();
    }

    /**
     * @return list<int>
     */
    public function descendantIds(Category $category): array
    {
        $map = $this->categories->parentMap();
        $children = [];

        foreach ($map as $id => $parent) {
            $children[$parent ?? 0][] = $id;
        }

        $result = [];
        $stack = $children[$category->id] ?? [];

        while ($stack !== []) {
            $id = array_pop($stack);
            $result[] = $id;
            array_push($stack, ...($children[$id] ?? []));
        }

        return $result;
    }

    public function create(CategoryData $data): Category
    {
        $this->assertParentAllowed(null, $data->parentId);

        $category = $this->transaction(fn (): Category => $this->categories->create([
            ...$this->attributes($data),
            'slug' => $this->uniqueSlug($data->slug ?? '', $data->name, null),
            'sort_order' => $data->sortOrder ?? $this->categories->nextSortOrder($data->parentId),
            'thumbnail_path' => $data->thumbnail?->store(Category::THUMBNAIL_DIRECTORY, Category::THUMBNAIL_DISK) ?: null,
        ]), $this->connection());

        if ($category->thumbnail_path !== null) {
            ConvertCategoryThumbnail::dispatch($category->id);
        }

        return $category;
    }

    public function update(Category $category, CategoryData $data): Category
    {
        $this->assertParentAllowed($category, $data->parentId);

        $attributes = [
            ...$this->attributes($data),
            'slug' => $this->uniqueSlug($data->slug ?? '', $data->name, $category->id),
        ];

        if ($data->parentId !== $category->parent_id) {
            $attributes['sort_order'] = $data->sortOrder ?? $this->categories->nextSortOrder($data->parentId);
        } elseif ($data->sortOrder !== null) {
            $attributes['sort_order'] = $data->sortOrder;
        }

        $previous = array_filter([$category->thumbnail_path, $category->thumbnail_webp_path, $category->thumbnail_avif_path]);
        $replaced = false;

        if ($data->thumbnail !== null) {
            $attributes['thumbnail_path'] = $data->thumbnail->store(Category::THUMBNAIL_DIRECTORY, Category::THUMBNAIL_DISK) ?: null;
            $attributes['thumbnail_webp_path'] = null;
            $attributes['thumbnail_avif_path'] = null;
            $replaced = true;
        } elseif (! $data->keepThumbnail) {
            $attributes['thumbnail_path'] = $attributes['thumbnail_webp_path'] = $attributes['thumbnail_avif_path'] = null;
            $replaced = true;
        }

        $this->categories->update($category, $attributes);

        if ($replaced) {
            Storage::disk(Category::THUMBNAIL_DISK)->delete($previous);

            if ($category->thumbnail_path !== null) {
                ConvertCategoryThumbnail::dispatch($category->id);
            }
        }

        return $category->refresh();
    }

    /**
     * Soft delete. A category with sub-categories must be emptied first; its product
     * links stay (restorable) but it disappears from every list.
     */
    public function delete(Category $category): void
    {
        if ($this->categories->hasChildren($category)) {
            throw new DomainException(__('catalog::categories.delete_has_children', ['name' => $category->name]));
        }

        $this->categories->delete($category);
    }

    public function setFeatured(Category $category, bool $featured): Category
    {
        return $this->categories->update($category, ['is_featured' => $featured]);
    }

    /**
     * Saves the whole tree from the drag-and-drop editor: nested [{id, children: […]}].
     * Every category must appear exactly once, so the result is always a valid,
     * cycle-free tree; depth is capped at MAX_DEPTH.
     *
     * @param  list<array{id: int|string, children?: list<array<string, mixed>>}>  $nodes
     */
    public function saveTree(array $nodes): void
    {
        $positions = [];
        $walk = function (array $nodes, ?int $parentId, int $depth) use (&$walk, &$positions): void {
            if ($nodes !== [] && $depth > self::MAX_DEPTH) {
                throw new DomainException(__('catalog::categories.too_deep', ['max' => self::MAX_DEPTH]));
            }

            foreach (array_values($nodes) as $index => $node) {
                $id = (int) ($node['id'] ?? 0);

                if (isset($positions[$id])) {
                    throw new DomainException(__('catalog::categories.tree_invalid'));
                }

                $positions[$id] = ['parent_id' => $parentId, 'sort_order' => $index];
                $walk((array) ($node['children'] ?? []), $id, $depth + 1);
            }
        };
        $walk($nodes, null, 1);

        $known = array_keys($this->categories->parentMap());
        sort($known);
        $sent = array_keys($positions);
        sort($sent);

        if ($known !== $sent) {
            throw new DomainException(__('catalog::categories.tree_stale'));
        }

        $this->transaction(fn () => $this->categories->savePositions($positions), $this->connection());
    }

    /**
     * @return Collection<int, Product>
     */
    public function products(Category $category): Collection
    {
        return $this->categories->productsInOrder($category);
    }

    /**
     * Product order inside a category: the ids must be exactly the category's products.
     *
     * @param  list<int>  $productIds
     */
    public function saveProductOrder(Category $category, array $productIds): void
    {
        $current = $this->categories->productIds($category);
        $sorted = $productIds;
        sort($current);
        sort($sorted);

        if ($current !== $sorted) {
            throw new DomainException(__('catalog::categories.products_stale'));
        }

        $this->transaction(fn () => $this->categories->saveProductOrder($category, $productIds), $this->connection());
    }

    private function assertParentAllowed(?Category $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($category !== null && ($parentId === $category->id || in_array($parentId, $this->descendantIds($category), true))) {
            throw new DomainException(__('catalog::categories.parent_cycle'));
        }

        $map = $this->categories->parentMap();

        if (! array_key_exists($parentId, $map)) {
            throw new DomainException(__('catalog::categories.parent_missing'));
        }

        $depth = 1;

        for ($cursor = $parentId; $cursor !== null; $cursor = $map[$cursor] ?? null) {
            $depth++;
        }

        if ($depth > self::MAX_DEPTH) {
            throw new DomainException(__('catalog::categories.too_deep', ['max' => self::MAX_DEPTH]));
        }
    }

    /**
     * @param  array<string, string>  $name
     */
    private function uniqueSlug(string $requested, array $name, ?int $ignoreId): string
    {
        $base = Str::slug($requested !== '' ? $requested : ($name['en'] ?? reset($name) ?: 'category'));
        $base = $base !== '' ? Str::limit($base, 180, '') : 'category';
        $slug = $base;

        for ($suffix = 2; $this->categories->slugExists($slug, $ignoreId); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CategoryData $data): array
    {
        return [
            'parent_id' => $data->parentId,
            'name' => $this->locales->forStorage($data->name),
            'description' => $this->locales->forStorage($data->description),
            'meta_title' => $this->locales->forStorage($data->metaTitle),
            'meta_description' => $this->locales->forStorage($data->metaDescription),
            'status' => $data->status,
            'is_featured' => $data->isFeatured,
            'show_in_menu' => $data->showInMenu,
        ];
    }

    private function connection(): ?string
    {
        return (new Category)->getConnectionName();
    }
}
