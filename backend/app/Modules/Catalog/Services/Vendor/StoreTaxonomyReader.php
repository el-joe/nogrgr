<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StoreCategoryRow;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreCategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Catalog\Support\StoreCategoryRef;

/**
 * Read side of the store taxonomy (decision #74) for the vendor pages and the storefront.
 *
 * Placement of a store product:
 *   central categories = `category_ids` when set, else the central product's categories;
 *   store categories   = `store_category_ids`;
 *   badges             = `badge_ids` when set, else the central product's badges.
 * A category lists its products in the store's order (`store_category_positions`), then
 * the global product sort. Central categories show when they hold a store product or the
 * store has a setting for them; store-own categories always show.
 */
class StoreTaxonomyReader
{
    /** @var array{rows: array<int, StoreProduct>, categories: array<int, list<int>>, badges: array<int, list<int>>}|null */
    private ?array $snapshot = null;

    /** Request the snapshot was built for (controllers are reused across requests in tests / Octane). */
    private ?int $snapshotRequest = null;

    public function __construct(
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreCategoryRepositoryContract $categories,
        private readonly StoreBadgeRepositoryContract $badgePositions,
        private readonly CategoryService $central,
    ) {}

    public function flush(): void
    {
        $this->snapshot = null;
    }

    /**
     * Every category of the store, merged and sorted (visible and hidden).
     *
     * @return list<StoreCategoryRow>
     */
    public function categories(bool $visibleOnly = false): array
    {
        $members = $this->memberships();
        $settings = $this->categories->centralSettings();
        $rows = [];

        foreach ($this->central->flatten() as $id => $node) {
            $category = $node['category'];
            $ref = StoreCategoryRef::central($id);

            if ($category->status !== RecordStatus::Active || (! isset($members[$ref->key()]) && ! isset($settings[$id]))) {
                continue;
            }

            $setting = $settings[$id] ?? null;
            $rows[] = new StoreCategoryRow(
                ref: $ref,
                name: (string) $category->name,
                path: $node['path'],
                imageUrl: $category->thumbnailUrl(),
                visible: ! ($setting !== null && $setting->is_hidden),
                featured: false,
                sortOrder: $setting?->sort_order,
                productIds: $this->ordered($ref, $members[$ref->key()] ?? []),
                central: $category,
            );
        }

        $own = $this->categories->allOrdered()->keyBy('id');

        foreach ($own as $category) {
            /** @var StoreCategory $category */
            $ref = StoreCategoryRef::store($category->id);
            $rows[] = new StoreCategoryRow(
                ref: $ref,
                name: (string) $category->name,
                path: $this->ownPath($category, $own->all()),
                imageUrl: $category->imageUrl(),
                visible: $category->is_active,
                featured: $category->is_featured,
                sortOrder: $category->sort_order,
                productIds: $this->ordered($ref, $members[$ref->key()] ?? []),
                own: $category,
            );
        }

        usort($rows, static fn (StoreCategoryRow $a, StoreCategoryRow $b): int => [$a->sortOrder ?? PHP_INT_MAX, $a->isOwn() ? 1 : 0, $a->path] <=> [$b->sortOrder ?? PHP_INT_MAX, $b->isOwn() ? 1 : 0, $b->path]);

        return $visibleOnly ? array_values(array_filter($rows, static fn (StoreCategoryRow $row): bool => $row->visible)) : $rows;
    }

    public function find(StoreCategoryRef $ref): ?StoreCategoryRow
    {
        foreach ($this->categories() as $row) {
            if ($row->ref->key() === $ref->key()) {
                return $row;
            }
        }

        if ($ref->isCentral()) {
            $node = $this->central->flatten()[$ref->id] ?? null;

            if ($node !== null && $node['category']->status === RecordStatus::Active) {
                return new StoreCategoryRow($ref, (string) $node['category']->name, $node['path'], $node['category']->thumbnailUrl(), true, false, null, [], central: $node['category']);
            }
        }

        return null;
    }

    /**
     * Effective central category ids of a store product.
     *
     * @return list<int>
     */
    public function centralCategoryIds(StoreProduct $product): array
    {
        if ($product->category_ids !== null) {
            return array_values(array_map('intval', $product->category_ids));
        }

        return $product->source === StoreProductSource::Central ? ($this->snapshot()['categories'][(int) $product->central_product_id] ?? []) : [];
    }

    /**
     * Effective badge ids of a store product.
     *
     * @return list<int>
     */
    public function badgeIds(StoreProduct $product): array
    {
        if ($product->badge_ids !== null) {
            return array_values(array_map('intval', $product->badge_ids));
        }

        return $product->source === StoreProductSource::Central ? ($this->snapshot()['badges'][(int) $product->central_product_id] ?? []) : [];
    }

    /**
     * Products on a badge: "admin" = inherited from the platform's badge list (read-only,
     * platform order), "store" = placed by the store (store order).
     *
     * @return array{admin: list<int>, store: list<int>}
     */
    public function badgeMembers(ProductBadge $badge): array
    {
        $admin = [];
        $store = [];

        foreach ($this->snapshot()['rows'] as $product) {
            if (! in_array($badge->id, $this->badgeIds($product), true)) {
                continue;
            }

            $product->badge_ids === null ? $admin[] = $product->id : $store[] = $product->id;
        }

        $positions = $this->badgePositions->positions($badge->id);

        return ['admin' => $admin, 'store' => $this->sortByPositions($store, $positions)];
    }

    /**
     * badge id => number of store products on it.
     *
     * @return array<int, int>
     */
    public function badgeCounts(): array
    {
        $counts = [];

        foreach ($this->snapshot()['rows'] as $product) {
            foreach ($this->badgeIds($product) as $badgeId) {
                $counts[$badgeId] = ($counts[$badgeId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Store products in a category, in category order.
     *
     * @return list<int>
     */
    public function productIds(StoreCategoryRef $ref): array
    {
        return $this->ordered($ref, $this->memberships()[$ref->key()] ?? []);
    }

    /**
     * category ref key => store product ids (global product order).
     *
     * @return array<string, list<int>>
     */
    private function memberships(): array
    {
        $members = [];

        foreach ($this->snapshot()['rows'] as $product) {
            foreach ($this->centralCategoryIds($product) as $id) {
                $members[StoreCategoryRef::central($id)->key()][] = $product->id;
            }

            foreach (array_map('intval', $product->store_category_ids ?? []) as $id) {
                $members[StoreCategoryRef::store($id)->key()][] = $product->id;
            }
        }

        return $members;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function ordered(StoreCategoryRef $ref, array $ids): array
    {
        return $this->sortByPositions($ids, $this->categories->positions($ref->key()));
    }

    /**
     * Positioned ids first (by position), the others keep their order.
     *
     * @param  list<int>  $ids
     * @param  array<int, int>  $positions
     * @return list<int>
     */
    private function sortByPositions(array $ids, array $positions): array
    {
        $index = array_flip($ids);
        usort($ids, static fn (int $a, int $b): int => [$positions[$a] ?? PHP_INT_MAX, $index[$a]] <=> [$positions[$b] ?? PHP_INT_MAX, $index[$b]]);

        return $ids;
    }

    /**
     * @param  array<int, StoreCategory>  $all
     */
    private function ownPath(StoreCategory $category, array $all): string
    {
        $names = [(string) $category->name];
        $seen = [$category->id];
        $parentId = $category->parent_id;

        while ($parentId !== null && isset($all[$parentId]) && ! in_array($parentId, $seen, true)) {
            $seen[] = $parentId;
            array_unshift($names, (string) $all[$parentId]->name);
            $parentId = $all[$parentId]->parent_id;
        }

        return implode(' › ', $names);
    }

    /**
     * @return array{rows: array<int, StoreProduct>, categories: array<int, list<int>>, badges: array<int, list<int>>}
     */
    private function snapshot(): array
    {
        $request = spl_object_id(app('request'));

        if ($this->snapshot !== null && $this->snapshotRequest === $request) {
            return $this->snapshot;
        }

        $this->snapshotRequest = $request;

        $rows = $this->products->placementRows()->keyBy('id')->all();
        $centralIds = [];

        foreach ($rows as $product) {
            if ($product->source === StoreProductSource::Central && $product->central_product_id !== null) {
                $centralIds[] = (int) $product->central_product_id;
            }
        }

        return $this->snapshot = [
            'rows' => $rows,
            'categories' => $this->products->centralCategoryMap($centralIds),
            'badges' => $this->products->centralBadgeMap($centralIds),
        ];
    }
}
