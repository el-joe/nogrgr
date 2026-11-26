<?php

namespace App\Modules\Storefront\DTOs\Catalog;

/**
 * One page of a listing plus the filter facets of the whole (unpaged) result.
 */
final readonly class ListingResult
{
    /**
     * @param  list<ProductCardData>  $cards
     * @param  array{price: list<array{value: string, label: string, count: int}>, rating: list<array{value: int, count: int}>, options: list<array{id: int, name: string, values: list<array{value: string, count: int}>}>, categories: list<array{id: int, name: string, count: int}>, in_stock: int}  $facets
     */
    public function __construct(
        public array $cards,
        public int $total,
        public int $page,
        public int $perPage,
        public array $facets,
    ) {}

    public function hasMore(): bool
    {
        return $this->page * $this->perPage < $this->total;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }
}
