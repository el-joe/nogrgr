<?php

namespace App\Modules\Storefront\DTOs\Catalog;

use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Enums\ListingSort;
use App\Modules\Storefront\Enums\SalesPeriod;
use App\Modules\Storefront\Services\StorefrontContext;
use Illuminate\Http\Request;

/**
 * Filters / sort / page of one listing request (query string of the listing pages and of
 * their "load more" fragments). Price ranges are USD minor units "min-max" (buckets the
 * filter panel offers, labelled in the display currency).
 */
final readonly class ListingQuery
{
    public const PER_PAGE = 20;

    /**
     * Allowed ?per= sizes (home grids page by 15 = 5 × 3).
     */
    public const PAGE_SIZES = [10, 15, 20];

    /**
     * @param  list<int>  $categoryIds  extra category filter (any of)
     * @param  array<int, list<string>>  $options  attribute id => option names (any of, per attribute)
     */
    public function __construct(
        public ListingKind $kind,
        public ListingSort $sort,
        public int $page = 1,
        public int $perPage = self::PER_PAGE,
        public ?string $term = null,
        public ?SalesPeriod $period = null,
        public array $categoryIds = [],
        public ?int $priceMin = null,
        public ?int $priceMax = null,
        public ?int $minRating = null,
        public bool $inStockOnly = false,
        public array $options = [],
        public ?int $addedWithinDays = null,
        public bool $flashOnly = false,
        public ?int $minDiscount = null,
        public bool $onSale = false,
        public bool $featuredOnly = false,
        public bool $outOfStockOnly = false,
        public ?string $minInput = null,
        public ?string $maxInput = null,
    ) {}

    public static function fromRequest(Request $request, ListingKind $kind, int $perPage = self::PER_PAGE): self
    {
        $sort = ListingSort::tryFrom((string) $request->query('sort'));
        $sort = $sort !== null && in_array($sort, $kind->sorts(), true) ? $sort : $kind->defaultSort();
        [$min, $max] = self::range($request->query('price'));
        /* Min / Max price fields are typed in the display currency; filtering compares USD minor units. */
        $minInput = self::amount($request->query('min'));
        $maxInput = self::amount($request->query('max'));

        if ($minInput !== null || $maxInput !== null) {
            if ($minInput !== null && $maxInput !== null && (float) $minInput > (float) $maxInput) {
                [$minInput, $maxInput] = [$maxInput, $minInput];
            }

            $rate = (float) (app(StorefrontContext::class)->currency()->rate ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;
            $min = $minInput !== null ? (int) floor((float) $minInput / $rate * 100) : null;
            $max = $maxInput !== null ? (int) ceil((float) $maxInput / $rate * 100) : null;
        }
        $options = [];

        foreach ((array) $request->query('opt', []) as $attribute => $values) {
            $values = array_values(array_filter(array_map('strval', (array) $values), static fn (string $value): bool => $value !== '' && mb_strlen($value) <= 120));

            if (is_numeric($attribute) && $values !== []) {
                $options[(int) $attribute] = array_slice($values, 0, 20);
            }
        }

        $rating = (int) $request->query('rating', 0);
        $added = (int) $request->query('added', 0);
        $discount = (int) $request->query('discount', 0);

        return new self(
            kind: $kind,
            sort: $sort,
            page: max(1, min(500, (int) $request->query('page', 1))),
            perPage: in_array((int) $request->query('per'), self::PAGE_SIZES, true) ? (int) $request->query('per') : $perPage,
            term: self::term($request->query('q')),
            period: SalesPeriod::tryFrom((int) $request->query('period', SalesPeriod::Days30->value)) ?? SalesPeriod::Days30,
            categoryIds: array_values(array_filter(array_map('intval', (array) $request->query('cat', [])))),
            priceMin: $min,
            priceMax: $max,
            minRating: $rating >= 1 && $rating <= 5 ? $rating : null,
            inStockOnly: $request->boolean('in_stock'),
            options: $options,
            addedWithinDays: in_array($added, [7, 30], true) ? $added : null,
            flashOnly: $request->boolean('flash'),
            minDiscount: in_array($discount, [10, 20, 30, 50], true) ? $discount : null,
            onSale: $request->boolean('sale'),
            featuredOnly: $request->boolean('featured'),
            outOfStockOnly: ! $request->boolean('in_stock') && $request->boolean('out_of_stock'),
            minInput: $minInput,
            maxInput: $maxInput,
        );
    }

    public static function term(mixed $value): ?string
    {
        $term = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';

        return $term !== '' ? mb_substr($term, 0, 100) : null;
    }

    /**
     * Number of active filters (chips / "Filter (n)").
     */
    public function activeFilters(): int
    {
        return count($this->categoryIds) + ($this->priceMin !== null || $this->priceMax !== null ? 1 : 0)
            + ($this->minRating !== null ? 1 : 0) + ($this->inStockOnly ? 1 : 0) + count($this->options)
            + ($this->addedWithinDays !== null ? 1 : 0) + ($this->flashOnly ? 1 : 0) + ($this->minDiscount !== null ? 1 : 0)
            + ($this->onSale ? 1 : 0) + ($this->featuredOnly ? 1 : 0) + ($this->outOfStockOnly ? 1 : 0);
    }

    private static function amount(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value) === 1 ? $value : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private static function range(mixed $value): array
    {
        if (! is_string($value) || preg_match('/^(\d{0,9})-(\d{0,9})$/', $value, $match) !== 1) {
            return [null, null];
        }

        return [$match[1] !== '' ? (int) $match[1] : null, $match[2] !== '' ? (int) $match[2] : null];
    }
}
