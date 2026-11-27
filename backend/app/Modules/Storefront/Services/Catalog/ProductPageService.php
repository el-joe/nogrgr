<?php

namespace App\Modules\Storefront\Services\Catalog;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\DTOs\Vendor\StoreVariantView;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Engagement\Services\Reviews\ReviewSummaryReader;
use App\Modules\Shipping\Services\DeliveryEstimateService;
use App\Modules\Shipping\Services\DeliveryPopupService;
use App\Modules\Shipping\Services\ShippingQuoteService;
use App\Modules\Shipping\Services\ShippingSettingsService;
use App\Modules\Storefront\DTOs\Catalog\IndexEntry;
use App\Modules\Storefront\DTOs\Catalog\ProductCardData;
use App\Modules\Storefront\DTOs\Catalog\ProductPageData;
use App\Modules\Storefront\Enums\ListingKind;
use App\Modules\Storefront\Repositories\Contracts\StorefrontCatalogRepositoryContract;
use App\Modules\Storefront\Services\StorefrontContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Product details page: the product as the cart sees it in the shopping country (null =
 * not sold there / inactive → 404), live prices per variant, delivery estimate + popup
 * days, free-shipping threshold, review summary and the related rail.
 */
class ProductPageService
{
    public function __construct(
        private readonly StorefrontCatalogRepositoryContract $repository,
        private readonly StoreCatalogReader $reader,
        private readonly ProductIndex $index,
        private readonly ProductListing $listing,
        private readonly ProductCards $cards,
        private readonly CategoryCatalog $categories,
        private readonly StorefrontContext $context,
        private readonly DeliveryEstimateService $estimates,
        private readonly DeliveryPopupService $popup,
        private readonly ShippingQuoteService $shipping,
        private readonly ShippingSettingsService $settings,
    ) {}

    public function find(int $id, ?int $variantId = null): ?ProductPageData
    {
        $countryId = $this->context->countryId();
        $product = $countryId !== null ? $this->repository->findActive($id) : null;

        if ($product === null || $countryId === null) {
            return null;
        }

        $view = $this->reader->view($product);
        $priced = $this->index->price($view, $countryId);

        if ($priced === null) {
            return null;
        }

        $variants = $this->variants($view, $priced['variants']);
        $selected = collect($variants)->firstWhere('id', $variantId) ?? collect($variants)->first(static fn (array $variant): bool => $variant['in_stock']) ?? ($variants[0] ?? null);
        $category = $this->categories->primaryFor($view->categoryIds);
        $entry = new IndexEntry($view->id(), $view->name, '', $view->sku, null, null, $priced['price'], $priced['regular'], $priced['compare'], null, $priced['in_stock'], $variants !== [], [], [], [], false, 0, 0, 0, 0, 0, 0);
        $slug = $view->product->slug ?: ($view->central->slug ?? '');
        $slug = $slug !== '' ? $slug : (Str::slug($view->name) ?: 'p');
        $flash = $priced['flash'] !== null ? $this->index->entry($view->id())?->flash : null;
        $images = $view->imageUrls;
        $media = $view->media !== [] ? $view->media : array_map(static fn (string $url): array => ['type' => 'image', 'url' => $url, 'poster' => null], $images);

        foreach ($view->variants as $variantView) {
            if ($variantView->imageUrl !== null && ! in_array($variantView->imageUrl, $images, true)) {
                $images[] = $variantView->imageUrl;
                $media[] = ['type' => 'image', 'url' => $variantView->imageUrl, 'poster' => null];
            }
        }

        $stock = $selected !== null ? ($priced['variants'][$selected['id']]['stock'] ?? null) : null;

        return new ProductPageData(
            view: $view,
            name: $view->name,
            slug: $slug,
            url: ProductCards::url($view->id(), $slug),
            description: $view->description,
            summary: $view->summary,
            images: $images,
            price: $selected['price'] ?? $this->context->formatMoney($priced['price']),
            priceMinor: $selected['price_minor'] ?? $priced['price'],
            compare: $selected !== null ? $selected['compare'] : ($priced['compare'] !== null && $priced['compare'] > $priced['price'] ? $this->context->formatMoney($priced['compare']) : null),
            discount: $selected !== null ? $selected['discount'] : $entry->discount(),
            flash: $flash ?? ($priced['flash'] !== null ? ['percentage' => $priced['flash']->percentage, 'starts_at' => '', 'ends_at' => ''] : null),
            inStock: $selected !== null ? $selected['in_stock'] : $priced['in_stock'],
            stock: $stock,
            attributes: $this->attributes($view),
            variants: $variants,
            selectedVariantId: $selected['id'] ?? null,
            breadcrumbs: $category !== null ? $this->categories->ancestry($category) : [],
            category: $category,
            bestSelling: in_array($view->id(), array_map(static fn (IndexEntry $item): int => $item->id, $this->listing->rail(ListingKind::BestSelling, 20)), true),
            soldCount: max((int) $view->product->sold_count, (int) ($view->central->sold_count ?? 0)),
            delivery: $this->delivery($countryId),
            deliveryDays: $this->settings->get()->showDeliveryPopup ? $this->popup->forStorefront($countryId) : [],
            freeShippingGrams: $this->shipping->freeShippingThreshold($countryId),
            reviews: $this->reviews($view->id()),
            related: $this->related($view->id(), $category),
            favorited: false,
            metaTitle: $view->product->meta_title ?: ($view->overrideText('meta_title') ?? $view->central->meta_title ?? null),
            metaDescription: $view->product->meta_description ?: ($view->overrideText('meta_description') ?? $view->central->meta_description ?? $view->summary),
            media: $media,
        );
    }

    /**
     * @param  array<int, array{price: int, regular: int, compare: int|null, stock: int|null}>  $prices
     * @return list<array{id: int, label: string, options: array<int, int>, price: string, compare: string|null, discount: int|null, price_minor: int, compare_minor: int|null, stock: int|null, in_stock: bool, image: string|null}>
     */
    private function variants(StoreProductView $view, array $prices): array
    {
        $rows = [];

        if ($view->isOwn()) {
            foreach ($view->product->variants->where('is_active', true) as $variant) {
                /** @var StoreProductVariant $variant */
                if (isset($prices[$variant->id])) {
                    $rows[] = $this->variantRow($variant->id, (string) $variant->title, [0 => $variant->id], $prices[$variant->id], null);
                }
            }

            return $rows;
        }

        foreach ($view->variants as $variant) {
            /** @var StoreVariantView $variant */
            if (isset($prices[$variant->id()])) {
                $options = [];

                foreach ($variant->central->options as $option) {
                    /** @var AttributeOption $option */
                    $options[$option->attribute_id] = $option->id;
                }

                $rows[] = $this->variantRow($variant->id(), $variant->label, $options, $prices[$variant->id()], $variant->imageUrl);
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $options
     * @param  array{price: int, regular: int, compare: int|null, stock: int|null}  $price
     * @return array{id: int, label: string, options: array<int, int>, price: string, compare: string|null, discount: int|null, price_minor: int, compare_minor: int|null, stock: int|null, in_stock: bool, image: string|null}
     */
    private function variantRow(int $id, string $label, array $options, array $price, ?string $image): array
    {
        $compare = $price['compare'] !== null && $price['compare'] > $price['price'] ? $price['compare'] : null;

        return [
            'id' => $id,
            'label' => $label,
            'options' => $options,
            'price' => $this->context->formatMoney($price['price']),
            'compare' => $compare !== null ? $this->context->formatMoney($compare) : null,
            'discount' => $compare !== null ? (int) round(($compare - $price['price']) / $compare * 100) : null,
            'price_minor' => $price['price'],
            'compare_minor' => $compare,
            'stock' => $price['stock'],
            'in_stock' => $price['stock'] === null || $price['stock'] > 0,
            'image' => $image,
        ];
    }

    /**
     * Variation pickers: central attributes of the variants (colour swatches when the
     * options carry one); own products get one picker of their variant titles.
     *
     * @return list<array{id: int, name: string, swatch: bool, options: list<array{id: int, name: string, swatch: string|null}>}>
     */
    private function attributes(StoreProductView $view): array
    {
        if ($view->isOwn()) {
            $options = $view->product->variants->where('is_active', true)
                ->map(static fn (StoreProductVariant $variant): array => ['id' => $variant->id, 'name' => (string) $variant->title, 'swatch' => null])
                ->values()->all();

            return $options === [] ? [] : [['id' => 0, 'name' => __st('product.option'), 'swatch' => false, 'options' => $options]];
        }

        $groups = [];

        foreach ($view->variants as $variant) {
            foreach ($variant->central->options as $option) {
                /** @var AttributeOption $option */
                $groups[$option->attribute_id][$option->id] = ['id' => $option->id, 'name' => (string) $option->name, 'swatch' => $option->swatch];
            }
        }

        if ($groups === []) {
            return [];
        }

        $names = Attribute::query()->whereIn('id', array_keys($groups))->orderBy('sort_order')->get()->keyBy('id');
        $attributes = [];

        foreach ($groups as $attributeId => $options) {
            $options = array_values($options);
            $attributes[] = [
                'id' => (int) $attributeId,
                'name' => (string) ($names->get($attributeId)->name ?? ''),
                'swatch' => collect($options)->every(static fn (array $option): bool => is_string($option['swatch']) && $option['swatch'] !== ''),
                'options' => $options,
            ];
        }

        return $attributes;
    }

    /**
     * @return array{label: string, min_date: string, max_date: string, company: string|null, cutoff_minutes: int|null, location: string|null}|null
     */
    private function delivery(int $countryId): ?array
    {
        $settings = $this->settings->get();

        if (! $settings->showEstimateOnProduct) {
            return null;
        }

        $estimate = $this->estimates->estimateFor($countryId);
        $format = static fn (CarbonImmutable $date): string => $date->locale(app()->getLocale())->translatedFormat('D j M');
        $label = $estimate->minDate->isSameDay($estimate->maxDate) ? $format($estimate->minDate) : $format($estimate->minDate).' – '.$format($estimate->maxDate);
        $cutoff = null;

        if ($settings->cutoffTime !== null && preg_match('/^(\d{1,2}):(\d{2})/', $settings->cutoffTime, $match) === 1) {
            $at = CarbonImmutable::now()->setTime((int) $match[1], (int) $match[2]);
            $cutoff = $at->isFuture() ? (int) CarbonImmutable::now()->diffInMinutes($at) : null;
        }

        return [
            'label' => $label,
            'min_date' => $estimate->minDate->toDateString(),
            'max_date' => $estimate->maxDate->toDateString(),
            'company' => $estimate->companyName,
            'cutoff_minutes' => $cutoff,
            'location' => $this->context->country()?->name,
        ];
    }

    /**
     * @return array{average: float, count: int, distribution: array<int, int>}
     */
    private function reviews(int $id): array
    {
        if (! class_exists(ReviewSummaryReader::class)) {
            return ['average' => 0.0, 'count' => 0, 'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
        }

        return app(ReviewSummaryReader::class)->summary($id);
    }

    /**
     * @return list<ProductCardData>
     */
    private function related(int $id, ?Category $category): array
    {
        $entries = $category !== null ? $this->listing->rail(ListingKind::Category, 10, $category, [$id]) : [];

        if (count($entries) < 10) {
            $taken = [$id, ...array_map(static fn (IndexEntry $entry): int => $entry->id, $entries)];
            $entries = [...$entries, ...$this->listing->rail(ListingKind::NewIn, 10 - count($entries), null, $taken)];
        }

        return $this->cards->make($entries);
    }
}
