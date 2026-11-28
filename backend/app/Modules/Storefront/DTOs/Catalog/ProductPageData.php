<?php

namespace App\Modules\Storefront\DTOs\Catalog;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\Category;

/**
 * Product details page (Figma 1:3380 / m 1:3073), priced live for the shopping country.
 */
final readonly class ProductPageData
{
    /**
     * @param  list<string>  $images  image URLs only (variant images appended)
     * @param  list<array{type: string, url: string, poster: string|null}>  $media  gallery slides: images and videos in order (#114)
     * @param  list<Category>  $breadcrumbs
     * @param  list<array{id: int, name: string, swatch: bool, options: list<array{id: int, name: string, swatch: string|null}>}>  $attributes
     * @param  list<array{id: int, label: string, options: array<int, int>, price: string, compare: string|null, discount: int|null, price_minor: int, compare_minor: int|null, stock: int|null, in_stock: bool, image: string|null}>  $variants
     * @param  array{label: string, min_date: string, max_date: string, company: string|null, cutoff_minutes: int|null, location: string|null}|null  $delivery
     * @param  list<array{day: int, percentage: int, date: string}>  $deliveryDays
     * @param  array{average: float, count: int, distribution: array<int, int>}  $reviews
     * @param  list<ProductCardData>  $related
     * @param  array{percentage: float, starts_at: string, ends_at: string}|null  $flash
     */
    public function __construct(
        public StoreProductView $view,
        public string $name,
        public string $slug,
        public string $url,
        public ?string $description,
        public ?string $summary,
        public array $images,
        public string $price,
        public int $priceMinor,
        public ?string $compare,
        public ?int $discount,
        public ?array $flash,
        public bool $inStock,
        public ?int $stock,
        public array $attributes,
        public array $variants,
        public ?int $selectedVariantId,
        public array $breadcrumbs,
        public ?Category $category,
        public bool $bestSelling,
        public int $soldCount,
        public ?array $delivery,
        public array $deliveryDays,
        public ?int $freeShippingGrams,
        public array $reviews,
        public array $related,
        public bool $favorited,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public array $media = [],
        public bool $relatedMore = false,
    ) {}

    /**
     * First gallery video (drives the floating video popup), null when there is none.
     *
     * @return array{type: string, url: string, poster: string|null}|null
     */
    public function firstVideo(): ?array
    {
        foreach ($this->media as $item) {
            if ($item['type'] === 'video') {
                return $item;
            }
        }

        return null;
    }
}
