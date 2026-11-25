<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the assign-products modal search.
 *
 * @mixin Product
 */
class BadgeProductSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'name' => (string) $product->name,
            'sku' => $product->sku,
            'category' => (string) ($product->categories->first()?->name ?? ''),
            'price' => $product->costBasis()->format(),
            'image' => $product->primaryImageUrl(),
        ];
    }
}
