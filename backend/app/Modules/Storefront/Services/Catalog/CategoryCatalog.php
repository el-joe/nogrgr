<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\DTOs\Vendor\StoreCategoryRow;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyReader;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store categories: the active central categories the store places products in (store
 * placement category_ids, else central, via StoreCatalogReader → ProductIndex) plus their
 * ancestors, limited to the store's selling categories when it has any. Feeds the header
 * mega-dropdown, the mobile categories browser, chips, category pages and the sitemap.
 */
class CategoryCatalog
{
    /**
     * @var Collection<int, Category>|null
     */
    private ?Collection $all = null;

    /**
     * @var array<int, true>|null
     */
    private ?array $visible = null;

    public function __construct(
        private readonly StorefrontCatalogRepositoryContract $repository,
        private readonly ProductIndex $index,
        private readonly StoreTaxonomyReader $taxonomy,
    ) {}

    /**
     * Visible categories keyed by id (tree order).
     *
     * @return Collection<int, Category>
     */
    public function visible(): Collection
    {
        $visible = $this->visibleIds();
        $order = $this->storeOrder();

        return $this->all()
            ->filter(static fn (Category $category): bool => isset($visible[$category->id]))
            ->sortBy(static fn (Category $category): int => $order[$category->id] ?? PHP_INT_MAX)
            ->keyBy('id');
    }

    /**
     * Top-level visible categories, each with `children` (visible sub-categories) set.
     *
     * @return list<array{category: Category, children: list<Category>}>
     */
    public function tree(): array
    {
        $visible = $this->visible();
        $tree = [];

        foreach ($visible as $category) {
            if ($category->parent_id === null || ! $visible->has($category->parent_id)) {
                $tree[] = [
                    'category' => $category,
                    'children' => $visible->filter(static fn (Category $child): bool => $child->parent_id === $category->id)->values()->all(),
                ];
            }
        }

        return $tree;
    }

    /**
     * Menu entries (show_in_menu) of the tree.
     *
     * @return list<array{category: Category, children: list<Category>}>
     */
    public function menu(): array
    {
        return array_values(array_filter($this->tree(), static fn (array $node): bool => $node['category']->show_in_menu));
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->visible()->first(static fn (Category $category): bool => $category->slug === $slug);
    }

    public function find(int $id): ?Category
    {
        return $this->all()->get($id);
    }

    /**
     * Visible direct children of a category.
     *
     * @return list<Category>
     */
    public function children(Category $category): array
    {
        return $this->visible()->filter(static fn (Category $child): bool => $child->parent_id === $category->id)->values()->all();
    }

    /**
     * The category and every descendant id (listing scope).
     *
     * @return list<int>
     */
    public function scopeIds(Category $category): array
    {
        $ids = [$category->id];
        $all = $this->all();
        $queue = [$category->id];

        while ($queue !== []) {
            $parent = array_shift($queue);

            foreach ($all as $child) {
                if ($child->parent_id === $parent && ! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Root → … → category chain (breadcrumbs).
     *
     * @return list<Category>
     */
    public function ancestry(Category $category): array
    {
        $chain = [$category];
        $guard = 0;

        while ($category->parent_id !== null && ($parent = $this->find($category->parent_id)) !== null && $guard++ < 10) {
            array_unshift($chain, $parent);
            $category = $parent;
        }

        return $chain;
    }

    /**
     * Deepest visible category of a product (breadcrumb / "in Category" link).
     *
     * @param  list<int>  $categoryIds
     */
    public function primaryFor(array $categoryIds): ?Category
    {
        $best = null;
        $depth = -1;
        $visible = $this->visibleIds();

        foreach ($categoryIds as $id) {
            $category = $this->find($id);

            if ($category !== null && isset($visible[$id]) && ($level = count($this->ancestry($category))) > $depth) {
                $best = $category;
                $depth = $level;
            }
        }

        return $best;
    }

    /**
     * @return Collection<int, Category>
     */
    private function all(): Collection
    {
        if ($this->all !== null) {
            return $this->all;
        }

        $categories = $this->repository->activeCategories();

        return $this->all = $categories->keyBy('id');
    }

    /**
     * @return array<int, true>
     */
    private function visibleIds(): array
    {
        if ($this->visible !== null) {
            return $this->visible;
        }

        $all = $this->all();
        $used = [];

        foreach ($this->index->entries() as $entry) {
            foreach ($entry->categoryIds as $id) {
                $used[$id] = true;
            }
        }

        $tenant = tenancy()->initialized ? tenant() : null;
        $selling = $tenant instanceof Tenant ? array_map('intval', $tenant->selling_category_ids ?? []) : [];
        $visible = [];

        foreach (array_keys($used) as $id) {
            $category = $all->get($id);
            $chain = [];
            $guard = 0;

            while ($category !== null && $guard++ < 10) {
                $chain[] = $category->id;
                $category = $category->parent_id !== null ? $all->get($category->parent_id) : null;
            }

            if ($chain === [] || ($selling !== [] && array_intersect($chain, $selling) === [])) {
                continue;
            }

            foreach ($chain as $chainId) {
                $visible[$chainId] = true;
            }
        }

        foreach ($this->taxonomy->categories() as $row) {
            if ($row->ref->isCentral() && ! $row->visible) {
                unset($visible[$row->ref->id]);
            }
        }

        return $this->visible = $visible;
    }

    /**
     * Store order of central categories (decision #74: hide / reorder central ones).
     *
     * @return array<int, int>
     */
    private function storeOrder(): array
    {
        $order = [];

        foreach (array_values(array_filter($this->taxonomy->categories(), static fn (StoreCategoryRow $row): bool => $row->ref->isCentral())) as $position => $row) {
            $order[$row->ref->id] = $position;
        }

        return $order;
    }
}
