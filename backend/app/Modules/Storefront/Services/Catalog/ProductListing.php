<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\Category;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\SalesPeriod;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use App\Modules\Storefront\Services\StorefrontContext;
use App\Modules\Storefront\Support\SwatchColor;
use BackedEnum;
use Carbon\CarbonImmutable;

/**
 * Listings, search and home rails over the ProductIndex (all sellable in the shopping
 * country): scope per entry point, filters, sort, page + facets of the whole result.
 *
 *   best selling = units sold in the period (paid orders), then lifetime sold count
 *   new in       = newest store products
 *   top rated    = average ≥ 4 then review count (needs reviews)
 *   offers       = products with a discount (live flash sale or list price)
 */
class ProductListing
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $sold = [];

    public function __construct(
        private readonly ProductIndex $index,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontCatalogRepositoryContract $repository,
        private readonly StorefrontContext $context,
    ) {}

    public function listing(ListingQuery $query, ?Category $category = null): ListingResult
    {
        $scoped = $this->scope($query, $category);
        $filtered = array_values(array_filter($scoped, fn (IndexEntry $entry): bool => $this->matches($entry, $query)));
        $sorted = $this->sort($filtered, $query, $category !== null && $query->sort === ListingSort::Recommended ? array_flip($this->categories->productOrder($category)) : []);
        $page = array_slice($sorted, ($query->page - 1) * $query->perPage, $query->perPage);

        return new ListingResult(
            cards: $this->cards->make($page),
            total: count($sorted),
            page: $query->page,
            perPage: $query->perPage,
            facets: $this->facets($scoped, $category),
        );
    }

    /**
     * A home / related rail: the first $limit entries of a scope (no filters).
     *
     * @param  list<int>  $exclude
     * @return list<IndexEntry>
     */
    public function rail(ListingKind $kind, int $limit, ?Category $category = null, array $exclude = []): array
    {
        $query = new ListingQuery(kind: $kind, sort: $kind->defaultSort(), perPage: $limit, period: SalesPeriod::Days30);
        $entries = array_values(array_filter($this->scope($query, $category), static fn (IndexEntry $entry): bool => ! in_array($entry->id, $exclude, true)));

        return array_slice($this->sort($entries, $query), 0, $limit);
    }

    /**
     * Products in a live flash sale (lightning deals), ending soonest first.
     *
     * @return list<IndexEntry>
     */
    public function flashDeals(int $limit): array
    {
        $entries = array_values(array_filter($this->index->entries(), static fn (IndexEntry $entry): bool => $entry->flash !== null));
        // A live sale always shows its products: sold-out ones go last (their card says so).
        usort($entries, static fn (IndexEntry $a, IndexEntry $b): int => [! $a->inStock, $a->flash['ends_at'] ?? '', -$a->soldCount] <=> [! $b->inStock, $b->flash['ends_at'] ?? '', -$b->soldCount]);

        return array_slice($entries, 0, $limit);
    }

    /**
     * Clearance: biggest list-price discounts that are not flash sales.
     *
     * @return list<IndexEntry>
     */
    public function clearance(int $limit): array
    {
        $entries = array_values(array_filter($this->index->entries(), static fn (IndexEntry $entry): bool => $entry->flash === null && $entry->discount() !== null && $entry->inStock));
        usort($entries, static fn (IndexEntry $a, IndexEntry $b): int => ($b->discount() ?? 0) <=> ($a->discount() ?? 0));

        return array_slice($entries, 0, $limit);
    }

    /**
     * Autocomplete: best matches of a term.
     *
     * @return list<IndexEntry>
     */
    public function suggest(string $term, int $limit = 6): array
    {
        $query = new ListingQuery(kind: ListingKind::Search, sort: ListingSort::Relevance, perPage: $limit, term: $term);

        return array_slice($this->sort($this->scope($query, null), $query), 0, $limit);
    }

    /**
     * @return list<IndexEntry>
     */
    private function scope(ListingQuery $query, ?Category $category): array
    {
        $entries = array_values($this->index->entries());

        return array_values(match ($query->kind) {
            ListingKind::Category => $category === null ? $entries : $this->inCategories($entries, $this->categories->scopeIds($category)),
            ListingKind::TopRated => array_filter($entries, static fn (IndexEntry $entry): bool => $entry->reviewCount > 0 && $entry->rating >= 4.0),
            ListingKind::Offers => array_filter($entries, static fn (IndexEntry $entry): bool => $entry->discount() !== null),
            ListingKind::Search => $query->term === null ? [] : array_filter($entries, fn (IndexEntry $entry): bool => $this->relevance($entry, $query->term) > 0),
            ListingKind::BestSelling => $this->bestSellingScope($entries, $query),
            ListingKind::NewIn => $entries,
        });
    }

    /**
     * Best-selling scope: products with real sales (units sold in the period or ever) plus the
     * store's Best-selling badge list. A store without either lists its in-stock products (the
     * old Ecommet behaviour), ranked the best-selling way, so the page is never empty and the
     * home grid and this page agree.
     *
     * @param  list<IndexEntry>  $entries
     * @return list<IndexEntry>
     */
    private function bestSellingScope(array $entries, ListingQuery $query): array
    {
        $sold = $this->soldIn($query->period);
        $badge = array_flip(app(BadgeShelves::class)->bestSellingIds());
        $qualifying = array_values(array_filter($entries, static fn (IndexEntry $entry): bool => ($sold[$entry->id] ?? 0) > 0 || $entry->soldCount > 0 || isset($badge[$entry->id])));

        return $qualifying !== [] ? $qualifying : array_values(array_filter($entries, static fn (IndexEntry $entry): bool => $entry->inStock));
    }

    private function matches(IndexEntry $entry, ListingQuery $query): bool
    {
        if ($query->categoryIds !== [] && $this->inCategories([$entry], $this->expand($query->categoryIds)) === []) {
            return false;
        }

        if (($query->priceMin !== null && $entry->price < $query->priceMin) || ($query->priceMax !== null && $entry->price > $query->priceMax)) {
            return false;
        }

        if ($query->minRating !== null && ($entry->reviewCount === 0 || $entry->rating < $query->minRating)) {
            return false;
        }

        if ($query->inStockOnly && ! $entry->inStock) {
            return false;
        }

        foreach ($query->options as $attribute => $values) {
            if (array_intersect($entry->options[$attribute] ?? [], $values) === []) {
                return false;
            }
        }

        if ($query->addedWithinDays !== null && $entry->createdAt < CarbonImmutable::now()->subDays($query->addedWithinDays)->getTimestamp()) {
            return false;
        }

        if ($query->flashOnly && $entry->flash === null) {
            return false;
        }

        if (($query->onSale && $entry->discount() === null) || ($query->featuredOnly && ! $entry->featured) || ($query->outOfStockOnly && $entry->inStock)) {
            return false;
        }

        return $query->minDiscount === null || ($entry->discount() ?? 0) >= $query->minDiscount;
    }

    /**
     * @param  list<IndexEntry>  $entries
     * @param  array<int, int>  $positions  store product id => category position
     * @return list<IndexEntry>
     */
    private function sort(array $entries, ListingQuery $query, array $positions = []): array
    {
        if ($positions !== []) {
            /* Category pages: the store's category order first (decision #74 positions), then Recommended. */
            usort($entries, static fn (IndexEntry $a, IndexEntry $b): int => [$positions[$a->id] ?? PHP_INT_MAX, $b->featured, $a->sortOrder, $b->soldCount] <=> [$positions[$b->id] ?? PHP_INT_MAX, $a->featured, $b->sortOrder, $a->soldCount]);

            return $entries;
        }

        $sold = $query->sort === ListingSort::BestSelling ? $this->soldIn($query->period) : [];
        // Best-selling ranking: the Best-selling badge list first (its own order), then units sold in the period, ever, rating.
        $badgePos = $query->sort === ListingSort::BestSelling ? array_flip(app(BadgeShelves::class)->bestSellingIds()) : [];
        $term = $query->term;

        usort($entries, fn (IndexEntry $a, IndexEntry $b): int => match ($query->sort) {
            ListingSort::Recommended => [$b->featured, $a->sortOrder, $b->soldCount] <=> [$a->featured, $b->sortOrder, $a->soldCount],
            ListingSort::Relevance => [$this->relevance($b, (string) $term), $b->soldCount] <=> [$this->relevance($a, (string) $term), $a->soldCount],
            ListingSort::BestSelling => [$badgePos[$a->id] ?? PHP_INT_MAX, $sold[$b->id] ?? 0, $b->soldCount, $b->rating, $b->createdAt] <=> [$badgePos[$b->id] ?? PHP_INT_MAX, $sold[$a->id] ?? 0, $a->soldCount, $a->rating, $a->createdAt],
            ListingSort::Newest => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id],
            ListingSort::PriceAsc => [$a->price, $a->id] <=> [$b->price, $b->id],
            ListingSort::PriceDesc => [$b->price, $a->id] <=> [$a->price, $b->id],
            ListingSort::Rating => [$b->rating, $b->reviewCount] <=> [$a->rating, $a->reviewCount],
            ListingSort::Discount => [$b->discount() ?? 0, $b->flash !== null] <=> [$a->discount() ?? 0, $a->flash !== null],
        });

        return $entries;
    }

    /**
     * 0 = no match; every word must appear in the name / SKU; prefix and exact matches rank higher.
     */
    private function relevance(IndexEntry $entry, string $term): int
    {
        $haystack = $entry->searchText();
        $needle = mb_strtolower(trim($term));

        if ($needle === '') {
            return 0;
        }

        foreach (preg_split('/\s+/u', $needle) ?: [] as $word) {
            if ($word !== '' && ! str_contains($haystack, $word)) {
                return 0;
            }
        }

        $name = mb_strtolower($entry->name);

        return 1 + (str_contains($name, $needle) ? 2 : 0) + (str_starts_with($name, $needle) ? 3 : 0) + ($name === $needle ? 5 : 0);
    }

    /**
     * @param  list<IndexEntry>  $entries
     * @param  list<int>  $ids
     * @return list<IndexEntry>
     */
    private function inCategories(array $entries, array $ids): array
    {
        $lookup = array_flip($ids);

        return array_values(array_filter($entries, static function (IndexEntry $entry) use ($lookup): bool {
            foreach ($entry->categoryIds as $id) {
                if (isset($lookup[$id])) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function expand(array $ids): array
    {
        $all = [];

        foreach ($ids as $id) {
            $category = $this->categories->find($id);
            $all = [...$all, ...($category !== null ? $this->categories->scopeIds($category) : [$id])];
        }

        return array_values(array_unique($all));
    }

    /**
     * @return array<int, int>
     */
    private function soldIn(?SalesPeriod $period): array
    {
        $days = ($period ?? SalesPeriod::Days30)->value;

        return $this->sold[$days] ??= $this->repository->unitsSoldSince(CarbonImmutable::now()->subDays($days));
    }

    /**
     * Facets of the scoped (unfiltered) result.
     *
     * @param  list<IndexEntry>  $entries
     * @return array{price: list<array{value: string, label: string, count: int}>, rating: list<array{value: int, count: int}>, options: list<array{id: int, name: string, values: list<array{value: string, count: int}>}>, categories: list<array{id: int, name: string, count: int}>, in_stock: int, out_of_stock: int, stars: array<int, int>, promo: array{sale: int, flash: int, featured: int}}
     */
    private function facets(array $entries, ?Category $category): array
    {
        $attributes = $this->index->attributes();
        $options = [];
        $categoryCounts = [];
        $inStock = 0;
        $starCounts = [4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $promo = ['sale' => 0, 'flash' => 0, 'featured' => 0];

        foreach ($entries as $entry) {
            $inStock += $entry->inStock ? 1 : 0;
            $promo['sale'] += $entry->discount() !== null ? 1 : 0;
            $promo['flash'] += $entry->flash !== null ? 1 : 0;
            $promo['featured'] += $entry->featured ? 1 : 0;

            foreach ($starCounts as $floor => $count) {
                $starCounts[$floor] += $entry->reviewCount > 0 && $entry->rating >= $floor ? 1 : 0;
            }

            foreach ($entry->options as $attribute => $values) {
                foreach ($values as $value) {
                    $options[$attribute][$value] = ($options[$attribute][$value] ?? 0) + 1;
                }
            }

            foreach ($entry->categoryIds as $id) {
                $categoryCounts[$id] = ($categoryCounts[$id] ?? 0) + 1;
            }
        }

        $candidates = $category !== null ? $this->categories->children($category) : array_map(static fn (array $node): Category => $node['category'], $this->categories->tree());
        $categoryFacet = [];

        foreach ($candidates as $candidate) {
            $count = count($this->inCategories($entries, $this->categories->scopeIds($candidate)));

            if ($count > 0) {
                $categoryFacet[] = ['id' => $candidate->id, 'name' => (string) $candidate->name, 'count' => $count];
            }
        }

        $ratings = [];

        foreach ([4, 3] as $stars) {
            $count = count(array_filter($entries, static fn (IndexEntry $entry): bool => $entry->reviewCount > 0 && $entry->rating >= $stars));

            if ($count > 0) {
                $ratings[] = ['value' => $stars, 'count' => $count];
            }
        }

        $optionFacet = [];
        $displayTypes = $options === [] ? [] : Attribute::query()->whereIn('id', array_keys($options))->pluck('display_type', 'id')->map(static fn ($type) => $type instanceof BackedEnum ? $type->value : (string) $type)->all();

        foreach ($options as $attribute => $values) {
            arsort($values);
            $name = $attributes[$attribute] ?? '#'.$attribute;
            $isColor = ($displayTypes[$attribute] ?? null) === 'color' || preg_match('/colou?r|لون/iu', $name) === 1;
            $optionFacet[] = [
                'id' => (int) $attribute,
                'name' => $name,
                'values' => array_map(static fn (string $value, int $count): array => ['value' => $value, 'count' => $count], array_keys($values), array_values($values)),
                'swatches' => $isColor ? $this->swatchGroups((int) $attribute, $values) : null,
            ];
        }

        return [
            'price' => $this->priceBuckets($entries),
            'rating' => $ratings,
            'options' => $optionFacet,
            'categories' => $categoryFacet,
            'in_stock' => $inStock,
            'out_of_stock' => count($entries) - $inStock,
            'stars' => $starCounts,
            'promo' => $promo,
        ];
    }

    /**
     * Colour facet as swatches: values of the same colour (stored swatch hex, else the colour named in
     * the value) merge into one swatch that filters by all of them; values without a colour become a
     * letter swatch when they are short names. At most 48 swatches, most products first.
     *
     * @param  array<string, int>  $values  option name => product count
     * @return list<array{label: string, hex: string|null, light: bool, count: int, values: list<string>}>
     */
    private function swatchGroups(int $attributeId, array $values): array
    {
        $stored = AttributeOption::query()->where('attribute_id', $attributeId)->whereNotNull('swatch')->get(['name', 'swatch'])
            ->mapWithKeys(static fn (AttributeOption $option): array => [(string) $option->name => (string) $option->swatch])->all();
        $groups = [];

        foreach ($values as $value => $count) {
            $value = (string) $value;
            $key = SwatchColor::key($stored[$value] ?? null, $value);

            if ($key === null) {
                if (mb_strlen($value) < 2 || mb_strlen($value) > 14 || preg_match('/^[#\d\W]/u', $value) === 1) {
                    continue;
                }

                $key = 'name:'.mb_strtolower($value);
            }

            $hex = str_starts_with($key, 'name:') ? null : SwatchColor::hex($stored[$value] ?? null, $value);
            $groups[$key] ??= ['label' => $value, 'hex' => $hex, 'light' => $hex !== null && SwatchColor::isLight($hex), 'count' => 0, 'values' => []];
            $groups[$key]['count'] += $count;
            $groups[$key]['values'][] = $value;
        }

        usort($groups, static fn (array $a, array $b): int => [$a['hex'] === null, -$a['count']] <=> [$b['hex'] === null, -$b['count']]);

        return array_slice($groups, 0, 48);
    }

    /**
     * Up to four price ranges by quartile of the result's prices (USD minor bounds, display labels).
     *
     * @param  list<IndexEntry>  $entries
     * @return list<array{value: string, label: string, count: int}>
     */
    private function priceBuckets(array $entries): array
    {
        $prices = array_map(static fn (IndexEntry $entry): int => $entry->price, $entries);
        sort($prices);

        if (count($prices) < 2 || $prices[0] === end($prices)) {
            return [];
        }

        $bounds = [];

        foreach ([0.25, 0.5, 0.75] as $quantile) {
            $value = $prices[(int) floor($quantile * (count($prices) - 1))];
            $value = (int) (ceil($value / 100) * 100);

            if (! in_array($value, $bounds, true) && $value > $prices[0] && $value < end($prices)) {
                $bounds[] = $value;
            }
        }

        $buckets = [];
        $lower = null;

        foreach ([...$bounds, null] as $upper) {
            $count = count(array_filter($prices, static fn (int $price): bool => ($lower === null || $price >= $lower) && ($upper === null || $price < $upper)));
            $label = match (true) {
                $lower === null => __st('catalog.filters.price_under', ['amount' => $this->context->formatMoney((int) $upper)]),
                $upper === null => __st('catalog.filters.price_over', ['amount' => $this->context->formatMoney($lower)]),
                default => $this->context->formatMoney($lower).' – '.$this->context->formatMoney($upper),
            };

            if ($count > 0) {
                $buckets[] = ['value' => ($lower ?? '').'-'.($upper !== null ? $upper - 1 : ''), 'label' => $label, 'count' => $count];
            }

            $lower = $upper;
        }

        return $buckets;
    }
}
