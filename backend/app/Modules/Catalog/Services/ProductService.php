<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\DTOs\ProductBulkData;
use App\Modules\Catalog\DTOs\ProductData;
use App\Modules\Catalog\DTOs\ProductVariantData;
use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Enums\MediaType;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductOfferedToStores;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Jobs\ConvertCatalogMedia;
use App\Modules\Catalog\Jobs\GenerateVideoPoster;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Inventory\Repositories\Contracts\BranchRepositoryContract;
use App\Modules\Inventory\Services\BranchService;
use App\Modules\Inventory\Services\StockService;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central products (ADM-010 / 011 / 012): create / edit across every section of the
 * admin form (#115: basics, primary image, gallery, translations, pricing & inventory,
 * categories, variants, badges, tenant assignments) and the importer, soft delete,
 * restore and the storefront order. Fires ProductCreated / ProductUpdated after commit,
 * and ProductOfferedToStores when stores newly see a published product (admin form).
 * Every product is sold in every country (no country availability, #115).
 */
class ProductService extends BaseService
{
    public const MEDIA_DISK = 'public';

    public const MEDIA_DIRECTORY = 'catalog/products';

    public const VARIANT_DIRECTORY = 'catalog/variants';

    public function __construct(
        private readonly ProductRepositoryContract $products,
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly BadgeService $badgeService,
        private readonly BranchRepositoryContract $branches,
        private readonly BranchService $branchService,
        private readonly StockService $stock,
        private readonly CatalogLocales $locales,
    ) {}

    /**
     * @return array<string, int>
     */
    public function tabCounts(): array
    {
        return $this->products->tabCounts();
    }

    public function create(ProductData $data): Product
    {
        $this->assertVariants(null, $data->variants);
        $product = $this->transaction(function () use ($data): Product {
            $product = $this->products->create([
                ...$this->columns($data),
                'slug' => $this->uniqueSlug($data->slug, $data->name, null),
                'sort_order' => $this->products->nextSortOrder(),
                'published_at' => $data->status->value === 'published' ? now() : null,
            ]);
            $this->syncEverything($product, $data);

            return $product;
        }, $this->connection());

        $this->afterSave($product, $data);
        $this->dispatchAfterCommit(new ProductCreated($product->id));
        $this->offerToStores($product, $data, null);

        return $product;
    }

    public function update(Product $product, ProductData $data): Product
    {
        $this->assertVariants($product, $data->variants);
        $before = [
            'published' => $product->status === ProductStatus::Published,
            'scope' => $product->assignment_scope,
            'tenants' => array_map('intval', $product->assignedTenants()->pluck('tenants.id')->all()),
        ];
        $this->transaction(function () use ($product, $data): void {
            $this->products->update($product, [
                ...$this->columns($data),
                'slug' => $this->uniqueSlug($data->slug, $data->name, $product->id),
                'published_at' => $data->status->value === 'published' ? ($product->published_at ?? now()) : $product->published_at,
            ]);
            $this->syncEverything($product, $data);
        }, $this->connection());

        $this->afterSave($product, $data);
        $this->dispatchAfterCommit(new ProductUpdated($product->id));
        $this->offerToStores($product, $data, $before);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);
        $this->dispatchAfterCommit(new ProductUpdated($product->id));
    }

    /**
     * Products list bulk action. Activate publishes (keeps the first published_at),
     * deactivate moves to draft, delete trashes; each changed product fires
     * ProductUpdated so tenant copies follow.
     *
     * @return int products changed
     */
    public function bulk(ProductBulkData $data): int
    {
        $products = $this->products->findManyForBulk($data->productIds);
        $target = $data->action->targetStatus();

        /** @var list<int> $changed */
        $changed = $this->transaction(function () use ($products, $target): array {
            $ids = [];

            foreach ($products as $product) {
                if ($target === null) {
                    $this->products->delete($product);
                    $ids[] = $product->id;

                    continue;
                }

                if ($product->status === $target) {
                    continue;
                }

                $this->products->update($product, [
                    'status' => $target,
                    'published_at' => $target === ProductStatus::Published ? ($product->published_at ?? now()) : $product->published_at,
                ]);
                $ids[] = $product->id;
            }

            return $ids;
        }, $this->connection());

        foreach ($changed as $id) {
            $this->dispatchAfterCommit(new ProductUpdated($id));
        }

        return count($changed);
    }

    public function restore(Product $product): void
    {
        if (! $product->trashed()) {
            throw new DomainException(__('catalog::products.not_trashed'));
        }

        $this->products->restore($product);
        $this->dispatchAfterCommit(new ProductUpdated($product->id));
    }

    /**
     * Saves the order of one loaded window of the sort page: the window keeps the
     * positions it held, re-distributed in the new order.
     *
     * @param  list<int>  $ids
     */
    public function saveOrder(array $ids): void
    {
        $this->transaction(function () use ($ids): void {
            if ($this->products->hasDuplicateSortOrders()) {
                $this->products->renumber();
            }

            $orders = $this->products->sortOrders($ids);

            if (count($orders) !== count($ids)) {
                throw new DomainException(__('catalog::products.sort.stale'));
            }

            $slots = array_values($orders);
            sort($slots);
            $this->products->saveSortOrders(array_combine($ids, $slots));
        }, $this->connection());
    }

    public function resetOrder(): void
    {
        $this->transaction(fn () => $this->products->renumber(true), $this->connection());
    }

    /**
     * @param  list<ProductVariantData>  $variants
     */
    private function assertVariants(?Product $product, array $variants): void
    {
        if ($variants === []) {
            return;
        }

        $optionIds = array_values(array_unique(array_merge(...array_map(static fn (ProductVariantData $variant): array => $variant->optionIds, $variants))));
        $attributeOf = AttributeOption::query()->whereKey($optionIds)->pluck('attribute_id', 'id')->map(static fn (mixed $id): int => (int) $id)->all();
        $signature = null;
        $keys = [];
        $existing = $product?->variants()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all() ?? [];

        foreach ($variants as $variant) {
            $attributes = array_map(static fn (int $option): ?int => $attributeOf[$option] ?? null, $variant->optionIds);

            if (in_array(null, $attributes, true) || count($attributes) !== count(array_unique($attributes))) {
                throw new DomainException(__('catalog::products.variants.one_per_attribute'));
            }

            sort($attributes);
            $signature ??= $attributes;

            if ($attributes !== $signature) {
                throw new DomainException(__('catalog::products.variants.same_attributes'));
            }

            if (isset($keys[$variant->key])) {
                throw new DomainException(__('catalog::products.variants.duplicate'));
            }

            $keys[$variant->key] = true;

            if ($variant->id !== null && ! in_array($variant->id, $existing, true)) {
                throw new DomainException(__('catalog::products.variants.unknown'));
            }

            if ($variant->sku !== null && $this->products->variantSkuTaken($variant->sku, $variant->id)) {
                throw new DomainException(__('catalog::products.variants.sku_taken', ['sku' => $variant->sku]));
            }
        }
    }

    private function syncEverything(Product $product, ProductData $data): void
    {
        $this->products->syncCategories($product, $data->categoryIds);
        $this->products->syncLabels($product, $data->labelIds);
        $this->products->syncTenants($product, $data->assignmentScope === AssignmentScope::Selected ? $data->tenantIds : []);
        $this->syncBadges($product, $data->badges);

        $this->syncVariants($product, $data);
    }

    /**
     * @param  list<string>  $types
     */
    private function syncBadges(Product $product, array $types): void
    {
        foreach (BadgeType::cases() as $type) {
            $badge = $this->badgeService->badge($type);
            $assigned = in_array($product->id, $this->badges->assignedProductIds($badge), true);

            if (in_array($type->value, $types, true) && ! $assigned) {
                $this->badges->append($badge, [$product->id]);
            } elseif (! in_array($type->value, $types, true) && $assigned) {
                $this->badges->remove($badge, $product->id);
            }
        }
    }

    private function syncVariants(Product $product, ProductData $data): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];
        $attributeIds = [];

        foreach (array_values($data->variants) as $position => $row) {
            /** @var ProductVariant|null $variant */
            $variant = $row->id !== null ? $existing->get($row->id) : null;
            $attributes = [
                'sku' => $row->sku,
                'price' => $row->priceMinor,
                'weight_grams' => $row->weightGrams,
                'position' => $position,
                'status' => $row->isActive ? RecordStatus::Active : RecordStatus::Inactive,
            ];
            $previousImage = $variant?->image_path;

            if ($row->image !== null) {
                $attributes['image_path'] = $row->image->store(self::VARIANT_DIRECTORY, self::MEDIA_DISK) ?: null;
            } elseif ($row->removeImage) {
                $attributes['image_path'] = null;
            }

            $saved = $this->products->saveVariant($product, $variant, $attributes, $row->optionIds);
            $kept[] = $saved->id;
            $attributeIds = [...$attributeIds, ...AttributeOption::query()->whereKey($row->optionIds)->pluck('attribute_id')->map(static fn (mixed $id): int => (int) $id)->all()];

            if (array_key_exists('image_path', $attributes) && $previousImage !== null) {
                Storage::disk(self::MEDIA_DISK)->delete($previousImage);
            }
        }

        $this->products->deleteVariants($product, array_values(array_diff(array_map('intval', $existing->keys()->all()), $kept)));
        $this->products->syncAttributes($product, array_values(array_unique($attributeIds)));
    }

    /**
     * Stores that see the published product now but did not before (published + offered
     * by the assignment scope) get the "new product for you" notification. Admin form
     * only; imports never notify.
     *
     * @param  array{published: bool, scope: AssignmentScope, tenants: list<int>}|null  $before  null = new product
     */
    private function offerToStores(Product $product, ProductData $data, ?array $before): void
    {
        if (! $data->notifyStores || $data->status !== ProductStatus::Published) {
            return;
        }

        $wasVisible = $before !== null && $before['published'];

        if ($data->assignmentScope === AssignmentScope::Selected) {
            $new = $wasVisible && $before['scope'] === AssignmentScope::All ? [] : array_values(array_diff($data->tenantIds, $wasVisible ? $before['tenants'] : []));

            if ($new !== []) {
                $this->dispatchAfterCommit(new ProductOfferedToStores($product->id, $new));
            }

            return;
        }

        if (! $wasVisible || $before['scope'] !== AssignmentScope::All) {
            $this->dispatchAfterCommit(new ProductOfferedToStores($product->id, allStores: true, exceptTenantIds: $wasVisible ? $before['tenants'] : []));
        }
    }

    /**
     * Gallery (with queued conversions) and stock run after the product rows are committed.
     */
    private function afterSave(Product $product, ProductData $data): void
    {
        $this->syncMedia($product, $data->primaryKeepId, $data->primaryImage, $data->keepMediaIds, $data->newImages);
        $stock = $data->stock;

        // Admin form quantities go to the default branch; other branches keep their levels
        // (managed under Shipping › Branches › Stock).
        if ($data->quantities !== null && ($branch = $this->branchService->defaultOrCreate()) !== null) {
            $stock[(int) $branch->id] = $data->quantities;
        }

        $this->saveStock($product, $stock);
    }

    /**
     * Primary image section first (sort 0, is_primary), then the gallery: kept items
     * (images and videos) then the new uploads. Without a primary image the first
     * gallery image is the primary one; a video never is (#114).
     *
     * @param  list<int>  $keepIds
     * @param  list<UploadedFile>  $uploads  images or videos (MediaType::fromUpload)
     */
    private function syncMedia(Product $product, ?int $primaryKeepId, ?UploadedFile $primaryUpload, array $keepIds, array $uploads): void
    {
        /** @var array<int, MediaType> $types */
        $types = $product->gallery()->get(['id', 'type'])->mapWithKeys(static fn (CatalogMedia $media): array => [(int) $media->id => $media->type])->all();
        $primaryKeepId = $primaryUpload === null && $primaryKeepId !== null && ($types[$primaryKeepId] ?? null) === MediaType::Image ? $primaryKeepId : null;
        $keepIds = array_values(array_diff(array_intersect($keepIds, array_keys($types)), [$primaryKeepId]));
        Storage::disk(self::MEDIA_DISK)->delete($this->products->pruneMedia($product, $primaryKeepId !== null ? [$primaryKeepId, ...$keepIds] : $keepIds));

        $changes = [];
        $hasPrimary = $primaryKeepId !== null || $primaryUpload !== null;
        $offset = $hasPrimary ? 1 : 0;

        if ($primaryKeepId !== null) {
            $changes[$primaryKeepId] = ['sort_order' => 0, 'is_primary' => true];
        }

        foreach ($keepIds as $position => $id) {
            $primary = ! $hasPrimary && $types[$id] === MediaType::Image;
            $hasPrimary = $hasPrimary || $primary;
            $changes[$id] = ['sort_order' => $position + $offset, 'is_primary' => $primary];
        }

        $this->products->updateMedia($product, $changes);
        $position = count($keepIds) + $offset;

        if ($primaryUpload !== null) {
            $this->storeUpload($product, $primaryUpload, 0, true);
        }

        foreach ($uploads as $upload) {
            $primary = ! $hasPrimary && MediaType::fromUpload($upload) === MediaType::Image;
            $hasPrimary = $hasPrimary || $primary;
            $this->storeUpload($product, $upload, $position++, $primary);
        }
    }

    private function storeUpload(Product $product, UploadedFile $upload, int $position, bool $primary): void
    {
        $type = MediaType::fromUpload($upload);
        $video = $type === MediaType::Video;
        [$width, $height] = $video ? [null, null] : (@getimagesize($upload->getRealPath()) ?: [null, null]);
        // Videos keep the declared container type (finfo reports some mp4 / mov as octet-stream).
        $mime = $video && in_array($upload->getClientMimeType(), MediaType::VIDEO_MIMES, true) ? $upload->getClientMimeType() : ($upload->getMimeType() ?: $upload->getClientMimeType());
        $path = $video
            ? $upload->storeAs(self::MEDIA_DIRECTORY, Str::uuid()->toString().'.'.MediaType::videoExtension($mime), self::MEDIA_DISK)
            : $upload->store(self::MEDIA_DIRECTORY, self::MEDIA_DISK);

        // store() answers false when the disk refuses the write (e.g. a directory the web
        // server cannot write to): never save a media row without its file.
        if (! is_string($path) || $path === '') {
            throw new DomainException(__('catalog::products.media_not_saved', ['name' => $upload->getClientOriginalName()]));
        }

        $media = $this->products->createMedia($product, [
            'type' => $type,
            'disk' => self::MEDIA_DISK,
            'path' => $path,
            'mime_type' => $mime,
            'size' => (int) $upload->getSize(),
            'width' => $width,
            'height' => $height,
            'conversion_status' => MediaConversionStatus::Pending,
            'is_primary' => $primary,
            'sort_order' => $position,
        ]);
        $video ? GenerateVideoPoster::dispatch($media->id) : ConvertCatalogMedia::dispatch($media->id);
    }

    /**
     * @param  array<int, array<string, int>>  $stock  [branch_id][variant key | "base"] => qty
     */
    private function saveStock(Product $product, array $stock): void
    {
        $variants = $product->variants()->with('options')->get();
        $keyToId = [];

        foreach ($variants as $variant) {
            $keyToId[VariantMatrix::key($variant->options->pluck('id')->all())] = $variant->id;
        }

        $branchIds = array_map('intval', $this->branches->query()->pluck('id')->all());
        $levels = [];

        foreach ($stock as $branchId => $cells) {
            if (! in_array((int) $branchId, $branchIds, true)) {
                continue;
            }

            foreach ($cells as $key => $quantity) {
                $variantKey = $key === 'base' ? 0 : ($keyToId[$key] ?? null);

                if ($variantKey !== null) {
                    $levels[(int) $branchId][$variantKey] = $quantity;
                }
            }
        }

        $this->stock->saveLevels($product, $levels, array_values($keyToId));
    }

    /**
     * @param  array<string, string>  $name
     */
    private function uniqueSlug(?string $requested, array $name, ?int $ignoreId): string
    {
        $base = Str::slug($requested !== null && $requested !== '' ? $requested : ($name['en'] ?? reset($name) ?: 'product'));
        $base = $base !== '' ? Str::limit($base, 170, '') : 'product';
        $slug = $base;

        for ($suffix = 2; $this->products->query()->withTrashed()->where('slug', $slug)->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(ProductData $data): array
    {
        $onSale = $data->compareAtMinor !== null && $data->compareAtMinor > $data->priceMinor;

        return [
            'name' => $this->locales->forStorage($data->name),
            'label' => $this->locales->forStorage($data->label),
            'summary' => $this->locales->forStorage($data->summary),
            'description' => $this->locales->forStorage($data->description),
            'meta_title' => $this->locales->forStorage($data->metaTitle),
            'meta_description' => $this->locales->forStorage($data->metaDescription),
            'meta_keywords' => $this->locales->forStorage($data->metaKeywords),
            'sku' => $data->sku,
            'status' => $data->status,
            'base_price' => $onSale ? $data->compareAtMinor : $data->priceMinor,
            'sale_price' => $onSale ? $data->priceMinor : null,
            'cost_price' => $data->costMinor,
            'weight_grams' => $data->weightGrams,
            'is_taxable' => $data->isTaxable,
            'factory' => $data->factory,
            'assignment_scope' => $data->assignmentScope,
            'manage_stock' => $data->manageStock,
            'min_stock' => $data->minStock,
            // Null = keep the current value (the admin form has no shipping section).
            ...($data->requiresShipping !== null ? ['requires_shipping' => $data->requiresShipping] : []),
            ...($data->deliveryScope !== null ? ['delivery_scope' => $data->deliveryScope] : []),
        ];
    }

    private function connection(): ?string
    {
        return (new Product)->getConnectionName();
    }
}
