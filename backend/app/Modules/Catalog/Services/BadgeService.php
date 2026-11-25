<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\DTOs\BadgeAssignmentData;
use App\Modules\Catalog\DTOs\BadgeSettingsData;
use App\Modules\Catalog\Enums\BadgeAutoFill;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Jobs\RefreshBadgeAutoFill;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductBadgeAssignment;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Badges hub (ADM-017…017g): one list per BadgeType (rows created on demand), products
 * assigned by search or a whole category (with sub-categories), removed, ordered by drag
 * and drop, plus the badge settings (label, ribbon, auto-fill).
 */
class BadgeService extends BaseService
{
    public function __construct(
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly ProductRepositoryContract $products,
        private readonly CategoryRepositoryContract $categories,
        private readonly CategoryService $categoryService,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * Upserts one row per BadgeType (idempotent).
     */
    public function ensureBadges(): void
    {
        foreach (BadgeType::cases() as $position => $type) {
            if ($this->badges->findByType($type) === null) {
                $this->badges->create([
                    'type' => $type,
                    'name' => $this->defaultName($type),
                    'is_active' => true,
                    'auto_fill' => BadgeAutoFill::Manual,
                    'auto_fill_limit' => 24,
                    'sort_order' => $position,
                ]);
            }
        }
    }

    public function badge(BadgeType $type): ProductBadge
    {
        $badge = $this->badges->findByType($type);

        if ($badge === null) {
            $this->ensureBadges();
            $badge = $this->badges->findByType($type);
        }

        return $badge ?? throw new DomainException(__('catalog::badges.missing'));
    }

    /**
     * Tabs: type value => ['badge' => ProductBadge, 'count' => int], in BadgeType order.
     *
     * @return array<string, array{badge: ProductBadge, count: int}>
     */
    public function tabs(): array
    {
        $this->ensureBadges();
        $counts = $this->badges->counts();
        $tabs = [];

        foreach (BadgeType::cases() as $type) {
            $badge = $this->badges->findByType($type);

            if ($badge !== null) {
                $tabs[$type->value] = ['badge' => $badge, 'count' => $counts[$badge->id] ?? 0];
            }
        }

        return $tabs;
    }

    /**
     * @return Collection<int, ProductBadgeAssignment>
     */
    public function assignments(ProductBadge $badge): Collection
    {
        return $this->badges->assignments($badge);
    }

    /**
     * Search for the assign modal (products already on the list are left out).
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchProducts(ProductBadge $badge, ?string $term, int $page = 1): LengthAwarePaginator
    {
        return $this->products->search($term, $this->badges->assignedProductIds($badge), 10, $page);
    }

    /**
     * @return int products added
     */
    public function assign(ProductBadge $badge, BadgeAssignmentData $data): int
    {
        $ids = $this->products->existingIds(array_values(array_unique(array_map('intval', $data->productIds))));

        if ($data->categoryId !== null) {
            /** @var Category $category */
            $category = $this->categories->findOrFail($data->categoryId);
            $ids = [...$ids, ...$this->products->idsInCategories([$category->id, ...$this->categoryService->descendantIds($category)])];
        }

        if ($ids === []) {
            throw new DomainException(__('catalog::badges.nothing_to_add'));
        }

        return $this->transaction(fn (): int => $this->badges->append($badge, array_values(array_unique($ids))), $this->connection());
    }

    public function remove(ProductBadge $badge, int $productId): void
    {
        if (! $this->badges->remove($badge, $productId)) {
            throw new DomainException(__('catalog::badges.not_assigned'));
        }
    }

    /**
     * @param  list<int>  $productIds  every product of the list, in the new order
     */
    public function saveOrder(ProductBadge $badge, array $productIds): void
    {
        $current = $this->badges->assignedProductIds($badge);
        $sorted = $productIds;
        sort($current);
        sort($sorted);

        if ($current !== $sorted) {
            throw new DomainException(__('catalog::badges.order_stale'));
        }

        $this->transaction(fn () => $this->badges->saveOrder($badge, $productIds), $this->connection());
    }

    public function updateSettings(ProductBadge $badge, BadgeSettingsData $data): ProductBadge
    {
        $this->badges->update($badge, [
            'name' => $this->locales->forStorage($data->name),
            'is_active' => $data->isActive,
            'auto_fill' => $data->autoFill,
            'auto_fill_limit' => $data->autoFillLimit,
        ]);

        if ($data->autoFill->isAutomatic()) {
            RefreshBadgeAutoFill::dispatch($badge->type);
        }

        return $badge;
    }

    /**
     * Replaces an automatic list with its current top products (manual lists are untouched).
     */
    public function refreshAutoFill(ProductBadge $badge): void
    {
        $ids = match ($badge->auto_fill) {
            BadgeAutoFill::Newest => $this->products->newestIds($badge->auto_fill_limit),
            BadgeAutoFill::TopSelling => $this->products->topSellingIds($badge->auto_fill_limit),
            BadgeAutoFill::Manual => null,
        };

        if ($ids !== null) {
            $this->transaction(fn () => $this->badges->replace($badge, $ids), $this->connection());
        }
    }

    public function refreshAllAutoFills(): void
    {
        foreach ($this->badges->allOrdered() as $badge) {
            $this->refreshAutoFill($badge);
        }
    }

    /**
     * @return array<string, string>
     */
    private function defaultName(BadgeType $type): array
    {
        $names = [];

        foreach (['en', 'ar'] as $locale) {
            $names[$locale] = (string) __('catalog::enums.badge_type.'.$type->value, [], $locale);
        }

        return $names;
    }

    private function connection(): ?string
    {
        return (new ProductBadge)->getConnectionName();
    }
}
