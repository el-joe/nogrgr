<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Services\Vendor\StoreTaxonomyReader;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;

/**
 * Product badges on the storefront (decision #74 / #79): the active central badges that
 * hold sellable products, each with its products in the store's badge order (the store's
 * own placements by their drag order, then the inherited platform ones in platform order,
 * StoreTaxonomyReader::badgeMembers()), and badge names for the card tags.
 */
class BadgeShelves
{
    /**
     * @var array<int, ProductBadge>|null
     */
    private ?array $badges = null;

    public function __construct(
        private readonly ProductBadgeRepositoryContract $repository,
        private readonly StoreTaxonomyReader $taxonomy,
        private readonly ProductIndex $index,
        private readonly ProductCards $cards,
    ) {}

    /**
     * @return list<array{id: int, name: string, cards: list<ProductCardData>}>
     */
    public function shelves(int $limit = 12): array
    {
        $shelves = [];

        foreach ($this->badges() as $badge) {
            $entries = $this->entries($badge, $limit);

            if ($entries !== []) {
                $shelves[] = ['id' => $badge->id, 'name' => (string) $badge->name, 'cards' => $this->cards->make($entries)];
            }
        }

        return $shelves;
    }

    /**
     * Sellable products of a badge in badge order.
     *
     * @return list<IndexEntry>
     */
    public function entries(ProductBadge $badge, int $limit = 12): array
    {
        $members = $this->taxonomy->badgeMembers($badge);
        $entries = $this->index->entries();
        $result = [];

        foreach ([...$members['store'], ...$members['admin']] as $id) {
            if (isset($entries[$id]) && ! isset($result[$id])) {
                $result[$id] = $entries[$id];
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        return array_values($result);
    }

    /**
     * Name of the first active badge of a product (card tag), or null.
     *
     * @param  list<int>  $badgeIds
     */
    public function label(array $badgeIds): ?string
    {
        $badges = $this->badges();

        foreach ($badgeIds as $id) {
            if (isset($badges[$id])) {
                return (string) $badges[$id]->name;
            }
        }

        return null;
    }

    /**
     * @return array<int, ProductBadge>
     */
    private function badges(): array
    {
        return $this->badges ??= $this->repository->allOrdered()
            ->filter(static fn (ProductBadge $badge): bool => $badge->is_active)
            ->keyBy('id')
            ->all();
    }
}
