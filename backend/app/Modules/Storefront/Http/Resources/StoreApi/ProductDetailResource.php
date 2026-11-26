<?php

namespace App\Modules\Storefront\Http\Resources\StoreApi;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Storefront\DTOs\Catalog\ProductPageData;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product details for the app (APP-008): the storefront product page data (priced live
 * for the shopping country exactly like the cart, flash sale included) with USD amounts +
 * display conversion, variation pickers, variants (price / stock / image), the delivery
 * estimate of the country, the free-shipping weight, the review summary and the related
 * rail.
 *
 * @property ProductPageData $resource
 */
class ProductDetailResource extends JsonResource
{
    public function __construct(ProductPageData $resource, private readonly bool $favorited = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;
        $money = app(MoneyPresenter::class);
        $selected = collect($page->variants)->firstWhere('id', $page->selectedVariantId);
        $compare = $selected !== null ? $selected['compare_minor'] : app(ProductIndex::class)->entry($page->view->id())?->compare;

        return [
            'id' => $page->view->id(),
            'name' => $page->name,
            'slug' => $page->slug,
            'url' => $page->url,
            'sku' => $page->view->sku,
            'summary' => $page->summary,
            'description' => $page->description,
            'images' => $page->images,
            'price' => $money->present($page->priceMinor),
            'compare_price' => $compare !== null && $compare > $page->priceMinor ? $money->present($compare) : null,
            'discount_percent' => $page->discount,
            'flash' => $page->flash,
            'in_stock' => $page->inStock,
            'stock' => $page->stock,
            'sold_count' => $page->soldCount,
            'best_selling' => $page->bestSelling,
            'favorited' => $this->favorited,
            'attributes' => $page->attributes,
            'variants' => array_map(static fn (array $variant): array => [
                'id' => $variant['id'],
                'label' => $variant['label'],
                'options' => (object) $variant['options'],
                'price' => $money->present($variant['price_minor']),
                'compare_price' => $variant['compare_minor'] !== null ? $money->present($variant['compare_minor']) : null,
                'discount_percent' => $variant['discount'],
                'in_stock' => $variant['in_stock'],
                'stock' => $variant['stock'],
                'image' => $variant['image'],
            ], $page->variants),
            'selected_variant_id' => $page->selectedVariantId,
            'category' => $page->category !== null ? (new CategoryResource($page->category))->toArray($request) : null,
            'breadcrumbs' => array_map(static fn (Category $category): array => ['id' => $category->id, 'name' => (string) $category->name, 'slug' => $category->slug], $page->breadcrumbs),
            'delivery' => $page->delivery,
            'delivery_days' => $page->deliveryDays,
            'free_shipping_grams' => $page->freeShippingGrams,
            'reviews' => [
                'average' => $page->reviews['average'],
                'count' => $page->reviews['count'],
                'distribution' => (object) $page->reviews['distribution'],
            ],
            'related' => CatalogCards::present($page->related, $request),
            'meta_title' => $page->metaTitle,
            'meta_description' => $page->metaDescription,
        ];
    }
}
