<?php

namespace App\Modules\Api\Http\Resources\Mobile;

use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product card for the app (wishlist): the storefront index entry of the store product
 * (central data read through the store's selection, never copied) with USD amounts and
 * the display conversion (`X-Currency`).
 *
 * @property IndexEntry $resource
 */
class ProductSummaryResource extends JsonResource
{
    public function __construct(IndexEntry $resource, private readonly bool $favorited = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;
        $money = app(MoneyPresenter::class);

        return [
            'id' => $entry->id,
            'name' => $entry->name,
            'slug' => $entry->slug,
            'url' => ProductCards::url($entry->id, $entry->slug),
            'image' => $entry->image,
            'image2' => $entry->image2,
            'price' => $money->present($entry->price),
            'compare_price' => $entry->compare !== null && $entry->compare > $entry->price ? $money->present($entry->compare) : null,
            'discount_percent' => $entry->discount(),
            'flash' => $entry->flash,
            'rating' => $entry->rating,
            'review_count' => $entry->reviewCount,
            'in_stock' => $entry->inStock,
            'stock_left' => $entry->stockLeft,
            'has_variants' => $entry->hasVariants,
            'favorited' => $this->favorited,
        ];
    }
}
