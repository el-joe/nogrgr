<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StoreCategoryData;
use App\Modules\Catalog\Events\BadgesChanged;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreCategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Support\StoreCategoryRef;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Write side of the store taxonomy (VEN-017 / VEN-018, decision #74): store-own
 * categories, visibility and order of central categories, product placement and order
 * inside a category, store badge lists. Central rows are never written or copied; a
 * product that still inherits its central placement (`category_ids` / `badge_ids` null)
 * gets the inherited ids written once the store changes it.
 */
class StoreTaxonomyService extends BaseService
{
    public const MAX_DEPTH = 3;

    public function __construct(
        private readonly StoreCategoryRepositoryContract $categories,
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreBadgeRepositoryContract $badgePositions,
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly StoreTaxonomyReader $reader,
    ) {}

    public function findOwn(int $id): StoreCategory
    {
        /** @var StoreCategory $category */
        $category = $this->categories->findOrFail($id);

        return $category;
    }

    /**
     * Store-own categories as id => path (product placement selects, VEN-012d / 016c).
     *
     * @return array<int, string>
     */
    public function ownCategoryOptions(): array
    {
        $options = [];

        foreach ($this->reader->categories() as $row) {
            if ($row->own !== null) {
                $options[$row->own->id] = $row->path;
            }
        }

        asort($options);

        return $options;
    }

    /**
     * Parent choices: store categories except the edited one and its descendants (max depth).
     *
     * @return array<int, string>
     */
    public function parentOptions(?StoreCategory $editing = null): array
    {
        $excluded = $editing !== null ? [$editing->id, ...$this->categories->descendantIds($editing->id)] : [];
        $options = [];

        foreach ($this->reader->categories() as $row) {
            if ($row->own === null || in_array($row->own->id, $excluded, true) || substr_count($row->path, ' › ') + 1 >= self::MAX_DEPTH) {
                continue;
            }

            $options[$row->own->id] = $row->path;
        }

        asort($options);

        return $options;
    }

    public function createCategory(StoreCategoryData $data): StoreCategory
    {
        $this->assertParent(null, $data->parentId);

        /** @var StoreCategory $category */
        $category = $this->transaction(fn (): StoreCategory => $this->categories->create([
            ...$this->attributes($data),
            'slug' => $this->uniqueSlug($data->slug, $data->name, null),
            'sort_order' => $data->sortOrder ?? $this->categories->nextSortOrder(),
            'image_path' => $data->image?->store(StoreCategory::IMAGE_DIRECTORY, StoreCategory::IMAGE_DISK) ?: null,
        ]));

        $this->reader->flush();

        return $category;
    }

    public function updateCategory(StoreCategory $category, StoreCategoryData $data): StoreCategory
    {
        $this->assertParent($category, $data->parentId);
        $previous = $category->image_path;

        $attributes = [
            ...$this->attributes($data),
            'slug' => $this->uniqueSlug($data->slug, $data->name, $category->id),
        ];

        if ($data->sortOrder !== null) {
            $attributes['sort_order'] = $data->sortOrder;
        }

        if ($data->image !== null) {
            $attributes['image_path'] = $data->image->store(StoreCategory::IMAGE_DIRECTORY, StoreCategory::IMAGE_DISK) ?: null;
        } elseif ($data->removeImage) {
            $attributes['image_path'] = null;
        }

        $this->categories->update($category, $attributes);

        if ($previous !== null && array_key_exists('image_path', $attributes)) {
            Storage::disk(StoreCategory::IMAGE_DISK)->delete($previous);
        }

        $this->reader->flush();

        return $category->refresh();
    }

    /**
     * Deletes a store category: its products lose the placement, sub-categories move up.
     */
    public function deleteCategory(StoreCategory $category): void
    {
        $ref = StoreCategoryRef::store($category->id);

        $this->transaction(function () use ($category, $ref): void {
            foreach ($this->products->placementRows() as $product) {
                $ids = array_map('intval', $product->store_category_ids ?? []);

                if (in_array($category->id, $ids, true)) {
                    $this->products->update($product, ['store_category_ids' => array_values(array_diff($ids, [$category->id]))]);
                }
            }

            $this->categories->forgetPosition($ref->key());
            $this->categories->delete($category);
        });

        if ($category->image_path !== null) {
            Storage::disk(StoreCategory::IMAGE_DISK)->delete($category->image_path);
        }

        $this->reader->flush();
    }

    public function setVisible(StoreCategoryRef $ref, bool $visible): void
    {
        if ($ref->isCentral()) {
            $this->assertCentralKnown($ref);
            $this->categories->saveCentralSetting($ref->id, ['is_hidden' => ! $visible]);
        } else {
            $this->categories->update($this->findOwn($ref->id), ['is_active' => $visible]);
        }

        $this->reader->flush();
    }

    public function setFeatured(StoreCategory $category, bool $featured): void
    {
        $this->categories->update($category, ['is_featured' => $featured]);
        $this->reader->flush();
    }

    /**
     * Saves the category order (VEN-017c): every listed category gets its position.
     *
     * @param  list<StoreCategoryRef>  $refs
     */
    public function sortCategories(array $refs): void
    {
        $known = array_map(static fn ($row): string => $row->ref->key(), $this->reader->categories());
        $keys = array_map(static fn (StoreCategoryRef $ref): string => $ref->key(), $refs);

        if (array_diff($known, $keys) !== [] || array_diff($keys, $known) !== []) {
            throw new DomainException(__('catalog::taxonomy.sort.stale'));
        }

        $this->transaction(function () use ($refs): void {
            foreach ($refs as $index => $ref) {
                $ref->isCentral()
                    ? $this->categories->saveCentralSetting($ref->id, ['sort_order' => $index + 1])
                    : $this->categories->update($this->findOwn($ref->id), ['sort_order' => $index + 1]);
            }
        });

        $this->reader->flush();
    }

    /**
     * Places products in a category; returns how many were added.
     *
     * @param  list<int>  $storeProductIds
     */
    public function addProducts(StoreCategoryRef $ref, array $storeProductIds): int
    {
        $this->assertCategory($ref);
        $added = 0;

        $products = $this->productsInOrder($storeProductIds);

        $this->transaction(function () use ($ref, $products, &$added): void {
            foreach ($products as $product) {
                if ($ref->isCentral()) {
                    $ids = $this->reader->centralCategoryIds($product);

                    if (! in_array($ref->id, $ids, true)) {
                        $this->products->update($product, ['category_ids' => [...$ids, $ref->id]]);
                        $added++;
                    }
                } else {
                    $ids = array_map('intval', $product->store_category_ids ?? []);

                    if (! in_array($ref->id, $ids, true)) {
                        $this->products->update($product, ['store_category_ids' => [...$ids, $ref->id]]);
                        $added++;
                    }
                }
            }
        });

        $this->reader->flush();

        return $added;
    }

    public function removeProduct(StoreCategoryRef $ref, int $storeProductId): void
    {
        $product = $this->products->findManyKeyed([$storeProductId])->first();

        if (! $product instanceof StoreProduct) {
            throw new DomainException(__('catalog::taxonomy.products.missing'));
        }

        $this->transaction(function () use ($ref, $product): void {
            if ($ref->isCentral()) {
                $this->products->update($product, ['category_ids' => array_values(array_diff($this->reader->centralCategoryIds($product), [$ref->id]))]);
            } else {
                $this->products->update($product, ['store_category_ids' => array_values(array_diff(array_map('intval', $product->store_category_ids ?? []), [$ref->id]))]);
            }

            $this->categories->forgetPosition($ref->key(), $product->id);
        });

        $this->reader->flush();
    }

    /**
     * Saves the product order of a category (exactly the products it holds).
     *
     * @param  list<int>  $storeProductIds
     */
    public function sortProducts(StoreCategoryRef $ref, array $storeProductIds): void
    {
        $this->assertSameSet($this->reader->productIds($ref), $storeProductIds);
        $this->categories->savePositions($ref->key(), $storeProductIds);
        $this->reader->flush();
    }

    /**
     * Puts products on a badge (VEN-018a): they become store-placed for every badge.
     *
     * @param  list<int>  $storeProductIds
     */
    public function addToBadge(ProductBadge $badge, array $storeProductIds): int
    {
        $added = [];
        $products = $this->productsInOrder($storeProductIds);

        $this->transaction(function () use ($badge, $products, &$added): void {
            foreach ($products as $product) {
                $ids = $this->reader->badgeIds($product);

                if ($product->badge_ids === null || ! in_array($badge->id, $ids, true)) {
                    $this->products->update($product, ['badge_ids' => array_values(array_unique([...$ids, $badge->id]))]);
                    $added[] = $product->id;
                }
            }

            $this->badgePositions->append($badge->id, $added);
        });

        $this->reader->flush();
        $this->badgesChanged();

        return count($added);
    }

    /**
     * Puts every product of a category on the badge ("Add category").
     */
    public function addCategoryToBadge(ProductBadge $badge, StoreCategoryRef $ref): int
    {
        $this->assertCategory($ref);
        $ids = $this->reader->productIds($ref);

        if ($ids === []) {
            throw new DomainException(__('catalog::taxonomy.badges.category_empty'));
        }

        return $this->addToBadge($badge, $ids);
    }

    /**
     * Takes a product off the badge (an inherited one gets its other central badges written).
     */
    public function removeFromBadge(ProductBadge $badge, int $storeProductId): void
    {
        $product = $this->products->findManyKeyed([$storeProductId])->first();

        if (! $product instanceof StoreProduct || ! in_array($badge->id, $this->reader->badgeIds($product), true)) {
            throw new DomainException(__('catalog::taxonomy.products.missing'));
        }

        $this->transaction(function () use ($badge, $product): void {
            $this->products->update($product, ['badge_ids' => array_values(array_diff($this->reader->badgeIds($product), [$badge->id]))]);
            $this->badgePositions->forget($badge->id, $product->id);
        });

        $this->reader->flush();
        $this->badgesChanged();
    }

    /**
     * Saves the store order of a badge (the store-placed products only).
     *
     * @param  list<int>  $storeProductIds
     */
    public function sortBadge(ProductBadge $badge, array $storeProductIds): void
    {
        $this->assertSameSet($this->reader->badgeMembers($badge)['store'], $storeProductIds);
        $this->badgePositions->savePositions($badge->id, $storeProductIds);
        $this->reader->flush();
        $this->badgesChanged();
    }

    /**
     * Active central badges in order (the badge tabs).
     *
     * @return list<ProductBadge>
     */
    public function badges(): array
    {
        return array_values($this->badges->allOrdered()->filter(static fn (ProductBadge $badge): bool => $badge->is_active)->all());
    }

    /**
     * The store's products for the given ids, in the given order; none found = stale ids.
     *
     * @param  list<int>  $ids
     * @return list<StoreProduct>
     */
    private function productsInOrder(array $ids): array
    {
        $found = $this->products->findManyKeyed($ids);
        $ordered = [];

        foreach (array_values(array_unique($ids)) as $id) {
            $product = $found->get($id);

            if ($product instanceof StoreProduct) {
                $ordered[] = $product;
            }
        }

        if ($ordered === []) {
            throw new DomainException(__('catalog::taxonomy.products.missing'));
        }

        return $ordered;
    }

    /**
     * @param  list<int>  $current
     * @param  list<int>  $given
     */
    private function assertSameSet(array $current, array $given): void
    {
        $given = array_values(array_unique(array_map('intval', $given)));

        if (count($given) !== count($current) || array_diff($current, $given) !== []) {
            throw new DomainException(__('catalog::taxonomy.sort.stale'));
        }
    }

    private function assertCategory(StoreCategoryRef $ref): void
    {
        if ($this->reader->find($ref) === null) {
            throw new DomainException(__('catalog::taxonomy.categories.missing'));
        }
    }

    private function assertCentralKnown(StoreCategoryRef $ref): void
    {
        $this->assertCategory($ref);
    }

    private function assertParent(?StoreCategory $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if (! array_key_exists($parentId, $this->parentOptions($category))) {
            throw new DomainException(__('catalog::taxonomy.categories.invalid_parent'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreCategoryData $data): array
    {
        $clean = static fn (array $values): array => array_filter(array_map(static fn (?string $value): ?string => $value !== null ? trim($value) : null, $values), static fn (?string $value): bool => $value !== null && $value !== '');

        return [
            'parent_id' => $data->parentId,
            'name' => $clean($data->name),
            'description' => $clean($data->description) ?: null,
            'meta_keywords' => $clean($data->metaKeywords) ?: null,
            'meta_description' => $clean($data->metaDescription) ?: null,
            'country_ids' => $data->countryIds,
            'is_active' => $data->isActive,
            'is_featured' => $data->isFeatured,
        ];
    }

    /**
     * @param  array<string, string|null>  $name
     */
    private function uniqueSlug(?string $slug, array $name, ?int $exceptId): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $base = Str::slug((string) ($slug !== null && $slug !== '' ? $slug : ($name[$fallback] ?? reset($name) ?: 'category')));
        $base = $base !== '' ? Str::limit($base, 180, '') : 'category';
        $candidate = $base;
        $suffix = 2;

        while ($this->categories->slugExists($candidate, $exceptId)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function badgesChanged(): void
    {
        $this->dispatchAfterCommit(new BadgesChanged(StoreCatalogService::tenantId()));
    }
}
