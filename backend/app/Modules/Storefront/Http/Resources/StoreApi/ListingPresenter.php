<?php

namespace App\Modules\Storefront\Http\Resources\StoreApi;

use App\Modules\Storefront\DTOs\Catalog\ListingQuery;
use App\Modules\Storefront\DTOs\Catalog\ListingResult;
use Illuminate\Http\Request;

/**
 * One listing page for the Store API: `items` (cards), `meta` (page) and the filter
 * `facets` of the whole result (price buckets in USD minor "min-max", labelled in the
 * display currency), plus the applied sort.
 */
final class ListingPresenter
{
    /**
     * @return array{items: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}, facets: array<string, mixed>, sort: string, sorts: list<string>}
     */
    public static function present(ListingResult $result, ListingQuery $query, Request $request): array
    {
        return [
            'items' => CatalogCards::present($result->cards, $request),
            'meta' => [
                'current_page' => $result->page,
                'last_page' => max(1, (int) ceil($result->total / max(1, $result->perPage))),
                'per_page' => $result->perPage,
                'total' => $result->total,
            ],
            'facets' => $result->facets,
            'sort' => $query->sort->value,
            'sorts' => array_map(static fn ($sort): string => $sort->value, $query->kind->sorts()),
        ];
    }
}
