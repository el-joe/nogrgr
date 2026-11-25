<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductBadgeAssignment;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<ProductBadge>
 */
class ProductBadgeRepository extends BaseRepository implements ProductBadgeRepositoryContract
{
    protected function model(): string
    {
        return ProductBadge::class;
    }

    public function findByType(BadgeType $type): ?ProductBadge
    {
        return $this->query()->where('type', $type->value)->first();
    }

    public function allOrdered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function assignments(ProductBadge $badge): Collection
    {
        return $this->assignmentQuery($badge)
            ->whereHas('product')
            ->with(['product.media', 'product.categories'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function assignedProductIds(ProductBadge $badge): array
    {
        return array_map('intval', $this->assignmentQuery($badge)->orderBy('sort_order')->orderBy('id')->pluck('product_id')->all());
    }

    public function counts(): array
    {
        /** @var array<int, int> $counts */
        $counts = ProductBadgeAssignment::query()
            ->where('country_id', ProductBadgeAssignment::ALL_COUNTRIES)
            ->whereHas('product')
            ->selectRaw('product_badge_id, COUNT(*) as total')
            ->groupBy('product_badge_id')
            ->pluck('total', 'product_badge_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }

    public function append(ProductBadge $badge, array $productIds): int
    {
        $existing = $this->assignedProductIds($badge);
        $next = $this->assignmentQuery($badge)->max('sort_order');
        $position = $next === null ? 0 : (int) $next + 1;
        $added = 0;

        foreach (array_values(array_unique($productIds)) as $productId) {
            if (in_array($productId, $existing, true)) {
                continue;
            }

            ProductBadgeAssignment::query()->create([
                'product_badge_id' => $badge->id,
                'product_id' => $productId,
                'country_id' => ProductBadgeAssignment::ALL_COUNTRIES,
                'sort_order' => $position++,
            ]);
            $added++;
        }

        return $added;
    }

    public function remove(ProductBadge $badge, int $productId): bool
    {
        return $this->assignmentQuery($badge)->where('product_id', $productId)->delete() > 0;
    }

    public function saveOrder(ProductBadge $badge, array $productIds): void
    {
        foreach (array_values($productIds) as $position => $productId) {
            $this->assignmentQuery($badge)->where('product_id', $productId)->update(['sort_order' => $position]);
        }
    }

    public function replace(ProductBadge $badge, array $productIds): void
    {
        $this->assignmentQuery($badge)->delete();
        $this->append($badge, $productIds);
    }

    /**
     * @return Builder<ProductBadgeAssignment>
     */
    private function assignmentQuery(ProductBadge $badge): Builder
    {
        return ProductBadgeAssignment::query()
            ->where('product_badge_id', $badge->id)
            ->where('country_id', ProductBadgeAssignment::ALL_COUNTRIES);
    }
}
