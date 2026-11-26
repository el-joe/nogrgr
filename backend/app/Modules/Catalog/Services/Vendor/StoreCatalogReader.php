<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\DTOs\Vendor\StoreVariantView;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\VariantOverride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;

/**
 * THE read path of the store catalog (decision #65): central product data is read from the
 * central DB by id (batched eager loads on the central connection) and merged in memory
 * with the store's override rows, per field: override when set, else central. Central
 * stock is always central (branches). Category / badge placement: store_products
 * `category_ids` / `badge_ids` when set, else the central ones. Every vendor page — and the
 * storefront / mobile API later — reads store products through this class; nothing is
 * copied into the tenant DB.
 *
 *   $reader->load($products);            // 4–6 queries for any number of products
 *   $view = $reader->view($product);     // StoreProductView
 */
class StoreCatalogReader
{
    public const PUBLIC_DISK = 'public';

    /**
     * Relations every read needs (central ones run on the central connection).
     *
     * @return array<array-key, mixed>
     */
    public static function relations(): array
    {
        return [
            'centralProduct',
            'centralProduct.media',
            'centralProduct.categories',
            'centralProduct.badges',
            'centralProduct.variants' => static fn (Relation $query) => $query->where('status', RecordStatus::Active)->with('options'),
            'override',
            'variantOverrides',
            'media',
            'variants',
        ];
    }

    /**
     * @template TCollection of Collection<int, StoreProduct>|StoreProduct
     *
     * @param  TCollection  $products
     * @return TCollection
     */
    public function load(Collection|StoreProduct $products): Collection|StoreProduct
    {
        return $products->loadMissing(self::relations());
    }

    /**
     * @param  Builder<StoreProduct>  $query
     * @return Builder<StoreProduct>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with(self::relations());
    }

    /**
     * @param  Collection<int, StoreProduct>  $products
     * @return list<StoreProductView>
     */
    public function views(Collection $products): array
    {
        $this->load($products);

        return $products->map(fn (StoreProduct $product): StoreProductView => $this->view($product))->values()->all();
    }

    public function view(StoreProduct $product): StoreProductView
    {
        $this->load($product);

        return $product->isOwn() ? $this->ownView($product) : $this->centralView($product);
    }

    private function centralView(StoreProduct $product): StoreProductView
    {
        $central = $product->centralProduct;
        $override = $product->override;
        $overrideImages = $override?->images;
        $imageUrls = is_array($overrideImages) && $overrideImages !== []
            ? array_map(static fn (string $path): string => Storage::disk(self::PUBLIC_DISK)->url($path), $overrideImages)
            : ($central?->media->map(static fn (CatalogMedia $media): string => $media->url())->values()->all() ?? []);
        /** @var \Illuminate\Support\Collection<int, VariantOverride> $variantOverrides */
        $variantOverrides = $product->variantOverrides->keyBy('variant_id');
        $variants = [];
        $productWeight = $override->weight_grams ?? (int) ($central->weight_grams ?? 0);

        foreach ($central->variants ?? [] as $variant) {
            /** @var ProductVariant $variant */
            $variant->setRelation('product', $central);
            $variantOverride = $variantOverrides->get($variant->id);
            // Per locale, no fallback: a translation run writes only its target language.
            $title = $variantOverride !== null ? (string) $variantOverride->getTranslation('title', app()->getLocale(), false) : '';
            $images = $variantOverride?->images;

            $variants[] = new StoreVariantView(
                central: $variant,
                override: $variantOverride,
                label: $title !== '' ? $title : $variant->label(),
                stock: $variant->stock,
                weightGrams: $variantOverride->weight_grams ?? ($variant->weight_grams ?? $productWeight),
                weightOverridden: $variantOverride?->weight_grams !== null || ($variant->weight_grams === null && $override?->weight_grams !== null),
                imageUrl: is_array($images) && $images !== [] ? Storage::disk(self::PUBLIC_DISK)->url($images[0]) : $variant->imageUrl(),
            );
        }

        $field = static function (string $name) use ($override, $central): ?string {
            $value = $override !== null ? (string) $override->getTranslation($name, app()->getLocale(), false) : '';

            return $value !== '' ? $value : ($central !== null ? (string) $central->getAttribute($name) : null);
        };

        return new StoreProductView(
            product: $product,
            central: $central,
            override: $override,
            name: (string) ($field('name') ?? '#'.$product->central_product_id),
            summary: $field('summary'),
            description: $field('description'),
            sku: $central?->sku,
            imageUrls: $imageUrls,
            stock: (int) ($central->stock ?? 0),
            weightGrams: $productWeight,
            weightOverridden: $override?->weight_grams !== null,
            categoryIds: $product->category_ids !== null ? array_map('intval', $product->category_ids) : ($central?->categories->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all() ?? []),
            badgeIds: $product->badge_ids !== null ? array_map('intval', $product->badge_ids) : ($central?->badges->pluck('id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all() ?? []),
            variants: $variants,
            available: $central !== null && ! $central->trashed() && $central->status === ProductStatus::Published,
        );
    }

    private function ownView(StoreProduct $product): StoreProductView
    {
        return new StoreProductView(
            product: $product,
            central: null,
            override: null,
            name: (string) $product->name,
            summary: $product->summary,
            description: $product->description,
            sku: $product->sku,
            imageUrls: $product->media->map(static fn (CatalogMedia $media): string => OwnProductService::imageUrl($media))->values()->all(),
            stock: (int) $product->stock,
            weightGrams: (int) $product->weight_grams,
            weightOverridden: false,
            categoryIds: array_map('intval', $product->category_ids ?? []),
            badgeIds: [],
            variants: [],
            available: true,
        );
    }
}
