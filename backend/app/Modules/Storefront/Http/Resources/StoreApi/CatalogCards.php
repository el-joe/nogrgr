<?php

namespace App\Modules\Storefront\Http\Resources\StoreApi;

use App\Modules\Api\Http\Resources\Mobile\ProductSummaryResource;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use Illuminate\Http\Request;

/**
 * Product cards of the Store API catalog: each storefront card resolved back to its index
 * entry (same priced data as the theme card, flash sale included) and presented with USD
 * amounts + the display conversion (`ProductSummaryResource`, shared with the wishlist).
 */
final class CatalogCards
{
    /**
     * @param  iterable<ProductCardData>  $cards
     * @return list<array<string, mixed>>
     */
    public static function present(iterable $cards, Request $request): array
    {
        $index = app(ProductIndex::class);
        $items = [];

        foreach ($cards as $card) {
            $entry = $index->entry($card->id);

            if ($entry === null) {
                continue;
            }

            $items[] = (new ProductSummaryResource($entry, $card->favorited))->toArray($request) + [
                'badge' => $card->badge,
                'sold_count' => $card->soldCount,
                'weight' => $card->weight,
                'star_seller' => $card->starSeller,
            ];
        }

        return $items;
    }
}
