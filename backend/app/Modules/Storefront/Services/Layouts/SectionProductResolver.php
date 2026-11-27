<?php

namespace App\Modules\Storefront\Services\Layouts;

use App\Modules\Catalog\Enums\StoreCategorySource;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Support\StoreCategoryRef;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\SectionProductSource;
use App\Modules\Storefront\Services\Catalog\BadgeShelves;
use App\Modules\Storefront\Services\Catalog\CategoryCatalog;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use App\Modules\Storefront\Services\Catalog\ProductListing;
use App\Modules\Storefront\Support\StoreOwnCategory;
use App\Modules\Storefront\Support\ThemeSectionLibrary;

/**
 * Product source of a page-builder section (VEN-032): category (store order of the
 * category incl. sub-categories), badge (badge order), manual picks (the vendor's order,
 * products no longer sellable in the country left out), newest, best selling — or the
 * theme's own data with a different item limit. Only sellable products of the shopping
 * country (ProductIndex).
 */
class SectionProductResolver
{
    /** Theme rail data key → listing behind it (for "theme default" with another limit). */
    private const DATA_KINDS = [
        'trending' => ListingKind::BestSelling,
        'best' => ListingKind::BestSelling,
        'newRail' => ListingKind::NewIn,
        'topRated' => ListingKind::TopRated,
        'offers' => ListingKind::Offers,
    ];

    public function __construct(
        private readonly ProductListing $listing,
        private readonly ProductIndex $index,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
        private readonly BadgeShelves $badges,
        private readonly ProductBadgeRepositoryContract $badgeRepository,
    ) {}

    /**
     * Cards of a section, or null = keep the theme's own data unchanged.
     *
     * @param  array<string, mixed>  $settings
     * @return list<ProductCardData>|null
     */
    public function cards(array $settings, ?string $dataKey): ?array
    {
        if (($settings['source'] ?? null) === SectionProductSource::Category->value) {
            return $this->category((string) ($settings['category'] ?? ''), isset($settings['limit']) ? (int) $settings['limit'] : ThemeSectionLibrary::LIMIT_DEFAULT);
        }

        $entries = $this->entries($settings, $dataKey);

        return $entries === null ? null : $this->cards->make($entries);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<IndexEntry>|null
     */
    public function entries(array $settings, ?string $dataKey): ?array
    {
        $limit = isset($settings['limit']) ? (int) $settings['limit'] : null;
        $source = SectionProductSource::tryFrom((string) ($settings['source'] ?? '')) ?? SectionProductSource::Default;
        $take = $limit ?? ThemeSectionLibrary::LIMIT_DEFAULT;

        return match ($source) {
            SectionProductSource::Default => $limit === null ? null : $this->themeData($dataKey, $take),
            SectionProductSource::NewIn => $this->listing->rail(ListingKind::NewIn, $take),
            SectionProductSource::BestSelling => $this->listing->rail(ListingKind::BestSelling, $take),
            SectionProductSource::Category => [],
            SectionProductSource::Badge => $this->badge((int) ($settings['badge'] ?? 0), $take),
            SectionProductSource::Manual => $this->manual(array_map('intval', (array) ($settings['products'] ?? [])), $take),
        };
    }

    /**
     * @return list<IndexEntry>|null
     */
    private function themeData(?string $dataKey, int $limit): ?array
    {
        if ($dataKey === 'deals') {
            return $this->listing->flashDeals($limit);
        }

        $kind = self::DATA_KINDS[$dataKey ?? ''] ?? null;

        return $kind === null ? null : $this->listing->rail($kind, $limit);
    }

    /**
     * Products of a category (and its sub-categories) in the store's category order.
     *
     * @return list<ProductCardData>
     */
    private function category(string $key, int $limit): array
    {
        $ref = StoreCategoryRef::tryParse($key);
        $category = $ref === null ? null : $this->categories->visible()->get($ref->source === StoreCategorySource::Central ? $ref->id : StoreOwnCategory::idFor($ref->id));

        return $category === null ? [] : $this->listing->listing(new ListingQuery(kind: ListingKind::Category, sort: ListingSort::Recommended, page: 1, perPage: $limit), $category)->cards;
    }

    /**
     * @return list<IndexEntry>
     */
    private function badge(int $id, int $limit): array
    {
        $badge = $id > 0 ? $this->badgeRepository->find($id) : null;

        return $badge instanceof ProductBadge && $badge->is_active ? $this->badges->entries($badge, $limit) : [];
    }

    /**
     * @param  list<int>  $ids
     * @return list<IndexEntry>
     */
    private function manual(array $ids, int $limit): array
    {
        $entries = $this->index->entries();
        $result = [];

        foreach ($ids as $id) {
            if (isset($entries[$id]) && count($result) < $limit) {
                $result[] = $entries[$id];
            }
        }

        return $result;
    }
}
