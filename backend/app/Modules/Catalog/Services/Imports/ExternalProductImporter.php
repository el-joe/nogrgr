<?php

namespace App\Modules\Catalog\Services\Imports;

use App\Modules\Catalog\DTOs\Imports\ExternalImageData;
use App\Modules\Catalog\DTOs\Imports\ExternalOptionData;
use App\Modules\Catalog\DTOs\Imports\ExternalProductData;
use App\Modules\Catalog\DTOs\Imports\ExternalVariantData;
use App\Modules\Catalog\DTOs\Imports\ImportItemOutcome;
use App\Modules\Catalog\DTOs\ProductData;
use App\Modules\Catalog\DTOs\ProductVariantData;
use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\CountryAvailability;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportLinkType;
use App\Modules\Catalog\Enums\ImportLogResult;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Repositories\Contracts\AttributeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductBadgeRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;
use App\Modules\Catalog\Services\BadgeService;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Support\GalleryMedia;
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Inventory\Services\BranchService;
use DomainException;
use Illuminate\Http\UploadedFile;

/**
 * Upserts one external product into the central catalog through ProductService (same
 * path as the admin editor: variants, gallery + queued conversions, stock, events).
 * Idempotent: the product is found by its external id (then by SKU), variants and
 * gallery images and videos through catalog_import_links, attributes / options by
 * external_source + external_id. Admin-only data (labels, badges, tenants, countries,
 * extra categories and images) is kept on update. Unchanged payloads are skipped.
 */
class ExternalProductImporter
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepositoryContract $products,
        private readonly AttributeRepositoryContract $attributes,
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly BadgeService $badgeService,
        private readonly BranchService $branches,
        private readonly CatalogImportRepositoryContract $imports,
        private readonly ExternalImageDownloader $downloader,
        private readonly ExternalCategoryCreator $categoryCreator,
    ) {}

    public function import(CatalogImportRun $run, ExternalProductData $item): ImportItemOutcome
    {
        $driver = $run->driver;
        $images = count($item->images);
        $skip = static fn (string $message, ?int $productId = null): ImportItemOutcome => new ImportItemOutcome(ImportLogResult::Skipped, $message, $productId, $images, $images);

        $this->imports->discoverCategories($driver, $item->categories);
        $mapping = $this->imports->categoryMapping($driver, array_map(static fn ($category): int => $category->externalId, $item->categories));
        $unmapped = array_values(array_filter($item->categories, static fn ($category): bool => ($mapping[$category->externalId] ?? null) === null));
        $categoryWarnings = [];

        // Missing categories are created from the source (an admin mapping always wins);
        // one that cannot be created never blocks the product.
        if ($unmapped !== [] && $run->autoCreates()) {
            foreach ($unmapped as $category) {
                try {
                    $mapping[$category->externalId] = $this->categoryCreator->resolve($driver, $category);
                } catch (DomainException $exception) {
                    $categoryWarnings[] = $exception->getMessage();
                }
            }

            $unmapped = [];
        }

        if ($unmapped !== []) {
            return $skip(__('catalog::imports.messages.category_unmapped', ['name' => implode(', ', array_map(static fn ($category): string => $category->displayName(), $unmapped))]));
        }

        $product = $this->locate($driver, $item);

        if (is_string($product)) {
            return $skip($product);
        }

        if ($product !== null) {
            if (! $run->updatesExisting()) {
                return $skip(__('catalog::imports.messages.exists'), $product->id);
            }

            if ($this->imports->checksum($driver, ImportLinkType::Product, $item->externalId) === $item->checksum()) {
                return $skip(__('catalog::imports.messages.unchanged'), $product->id);
            }
        }

        $isNew = $product === null;
        $previousPrice = $product?->costBasis();
        try {
            $variants = $this->variantRows($driver, $product, $item, $run->autoCreates());
        } catch (DomainException $exception) {
            return $skip($exception->getMessage(), $product?->id);
        }

        [$keepMediaIds, $downloads, $warnings, $linkedImages] = $this->gallery($driver, $product, $item);
        $downloadedImages = count(array_filter($downloads, static fn (array $download): bool => ! str_starts_with($download['file']->getClientOriginalName(), 'video-')));
        $warnings = [...$categoryWarnings, ...$warnings];
        $stock = $this->stockCells($item, $variants, $warnings);

        try {
            $data = $this->productData($product, $item, array_values(array_filter($mapping)), $variants, $keepMediaIds, array_column($downloads, 'file'), $stock);
            $mediaBefore = $product !== null ? array_map('intval', $product->gallery()->pluck('id')->all()) : [];
            $product = $product === null ? $this->productService->create($data) : $this->productService->update($product, $data);
        } finally {
            foreach ($downloads as $download) {
                @unlink($download['file']->getRealPath());
            }

            foreach ($variants as $variant) {
                if ($variant['row']->image !== null) {
                    @unlink($variant['row']->image->getRealPath());
                }
            }
        }

        $this->products->update($product, ['external_source' => $driver->value, 'external_id' => $item->externalId]);
        $this->linkRecords($driver, $product, $item, $variants, $mediaBefore, array_column($downloads, 'external_id'));
        $this->imports->saveLink($driver, ImportLinkType::Product, $item->externalId, $product->id, $item->checksum());

        $summary = trans_choice('catalog::imports.messages.summary', count($variants), ['variants' => count($variants), 'images' => $images]);
        $videos = count($item->videos);

        if ($videos > 0) {
            $summary .= ' · '.trans_choice('catalog::imports.messages.videos', $videos, ['count' => $videos]);
        }
        $newPrice = $product->refresh()->costBasis();

        if (! $isNew && $previousPrice !== null && ! $previousPrice->equals($newPrice)) {
            $summary = __('catalog::imports.messages.price_changed', ['from' => $previousPrice->format('en'), 'to' => $newPrice->format('en')]);
        }

        return new ImportItemOutcome(
            $isNew ? ImportLogResult::Created : ImportLogResult::Updated,
            $summary,
            $product->id,
            $images,
            min($images, $linkedImages + $downloadedImages),
            $warnings,
        );
    }

    /**
     * Existing product of the item, null for a new one, or the reason to skip it.
     */
    private function locate(ImportDriver $driver, ExternalProductData $item): Product|string|null
    {
        $linked = $this->imports->linkedId($driver, ImportLinkType::Product, $item->externalId);
        $query = $this->products->query()->withTrashed();
        /** @var Product|null $product */
        $product = $linked !== null
            ? (clone $query)->find($linked)
            : (clone $query)->where('external_source', $driver->value)->where('external_id', $item->externalId)->first();

        if ($product === null) {
            /** @var Product|null $product */
            $product = (clone $query)->where('sku', $item->sku)->first();

            if ($product !== null && $product->external_id !== null && ($product->external_source !== $driver->value || $product->external_id !== $item->externalId)) {
                return __('catalog::imports.messages.duplicate_sku', ['sku' => $item->sku]);
            }
        } elseif ($product->sku !== $item->sku && $this->products->query()->withTrashed()->where('sku', $item->sku)->whereKeyNot($product->id)->exists()) {
            return __('catalog::imports.messages.duplicate_sku', ['sku' => $item->sku]);
        }

        if ($product?->trashed()) {
            return __('catalog::imports.messages.trashed');
        }

        return $product;
    }

    /**
     * @return list<array{external: ExternalVariantData, row: ProductVariantData}>
     */
    private function variantRows(ImportDriver $driver, ?Product $product, ExternalProductData $item, bool $create = true): array
    {
        $withOptions = array_values(array_filter($item->variants, static fn (ExternalVariantData $variant): bool => $variant->options !== []));

        if ($withOptions === []) {
            return [];
        }

        $existing = $product?->variants()->with('options')->get()->keyBy('id') ?? collect();
        $linked = $this->imports->linkedIds($driver, ImportLinkType::Variant, array_map(static fn (ExternalVariantData $variant): int => $variant->externalId, $withOptions));
        $used = [];
        $rows = [];

        foreach ($withOptions as $variant) {
            $optionIds = array_map(fn (ExternalOptionData $option): int => $this->optionId($driver, $option, $create), $variant->options);
            sort($optionIds);
            $key = VariantMatrix::key($optionIds);

            /** @var ProductVariant|null $match */
            $match = $existing->get($linked[$variant->externalId] ?? 0)
                ?? $existing->first(static fn (ProductVariant $candidate): bool => ! isset($used[$candidate->id]) && VariantMatrix::key($candidate->options->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()) === $key)
                ?? ($variant->sku !== null ? $existing->first(static fn (ProductVariant $candidate): bool => ! isset($used[$candidate->id]) && $candidate->sku === $variant->sku) : null);
            $matchId = $match !== null && ! isset($used[$match->id]) ? $match->id : null;

            if ($matchId !== null) {
                $used[$matchId] = true;
            }

            $rows[] = [
                'external' => $variant,
                'row' => new ProductVariantData(
                    key: $key,
                    id: $matchId,
                    optionIds: $optionIds,
                    sku: $this->variantSku($variant, $matchId),
                    priceMinor: $variant->priceMinor,
                    weightGrams: $variant->weightGrams,
                    // New variants are active; existing ones keep their status.
                    isActive: $matchId !== null && $match !== null ? $match->status === RecordStatus::Active : true,
                    image: $variant->imageUrl !== null && ($match === null || $matchId === null || $match->image_path === null)
                        ? $this->downloader->download($variant->imageUrl, 'variant-'.$variant->externalId)
                        : null,
                ),
            ];
        }

        return $rows;
    }

    private function variantSku(ExternalVariantData $variant, ?int $variantId): ?string
    {
        if ($variant->sku === null) {
            return null;
        }

        foreach ([$variant->sku, mb_substr($variant->sku, 0, 170).'-'.$variant->externalId] as $candidate) {
            if (! $this->products->variantSkuTaken($candidate, $variantId)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Local option of an external attribute value (attribute and option created on first
     * sight, with the option's colour swatch when the source has one).
     *
     * @throws DomainException when it does not exist and `$create` is off
     */
    private function optionId(ImportDriver $driver, ExternalOptionData $option, bool $create = true): int
    {
        /** @var Attribute|null $attribute */
        $attribute = $this->attributes->query()->withTrashed()->where('external_source', $driver->value)->where('external_id', $option->attributeExternalId)->first();
        $unknown = static fn (): DomainException => new DomainException(__('catalog::imports.errors.option_unknown', ['name' => ($option->attributeName['en'] ?? reset($option->attributeName)).': '.($option->name['en'] ?? reset($option->name))]));

        if ($attribute === null && ! $create) {
            throw $unknown();
        }

        if ($attribute === null) {
            /** @var Attribute $attribute */
            $attribute = $this->attributes->create([
                'name' => $option->attributeName,
                'display_type' => $option->swatch !== null ? AttributeDisplayType::Color : AttributeDisplayType::Text,
                'status' => RecordStatus::Active,
                'sort_order' => $this->attributes->nextSortOrder(),
                'external_source' => $driver->value,
                'external_id' => $option->attributeExternalId,
            ]);
        } elseif ($attribute->trashed()) {
            $attribute->restore();
        }

        /** @var AttributeOption|null $local */
        $local = AttributeOption::query()->withTrashed()->where('external_source', $driver->value)->where('external_id', $option->externalId)->first();

        if ($local === null && ! $create) {
            throw $unknown();
        }

        if ($local === null) {
            $local = $this->attributes->createOption($attribute, [
                'name' => $option->name,
                'swatch' => $option->swatch,
                'is_active' => true,
                'sort_order' => (int) $attribute->options()->max('sort_order') + 1,
                'external_source' => $driver->value,
                'external_id' => $option->externalId,
            ]);
        } elseif ($local->trashed()) {
            $local->restore();
        }

        return (int) $local->id;
    }

    /**
     * Kept gallery ids (imported images still in the source first, then imported videos,
     * then admin uploads), the new files to download (images, then videos), warnings and
     * the number of images already present. Videos are matched by external id like
     * images (same Media links), so a re-run never duplicates them (#114).
     *
     * @return array{0: list<int>, 1: list<array{external_id: int, file: UploadedFile}>, 2: list<string>, 3: int}
     */
    private function gallery(ImportDriver $driver, ?Product $product, ExternalProductData $item): array
    {
        $own = $product !== null ? array_map('intval', $product->gallery()->pluck('id')->all()) : [];
        $linkedByLocal = $this->imports->externalIdsFor($driver, ImportLinkType::Media, $own);
        $localByExternal = array_flip($linkedByLocal);
        $keep = [];
        $downloads = [];
        $warnings = [];
        $linkedKept = 0;

        foreach ([[$item->images, false], [$item->videos, true]] as [$entries, $isVideo]) {
            foreach ($entries as $entry) {
                if (isset($localByExternal[$entry->externalId])) {
                    $keep[] = $localByExternal[$entry->externalId];
                    $linkedKept += $isVideo ? 0 : 1;

                    continue;
                }

                $file = $isVideo ? $this->downloadVideo($entry) : $this->download($entry);

                if ($file === null) {
                    $warnings[] = $this->text($isVideo ? 'catalog::imports.messages.video_failed' : 'catalog::imports.messages.image_failed', ['url' => mb_substr($entry->url, 0, 200)]);

                    continue;
                }

                $downloads[] = ['external_id' => $entry->externalId, 'file' => $file];
            }
        }

        foreach ($own as $mediaId) {
            if (! isset($linkedByLocal[$mediaId])) {
                $keep[] = $mediaId;
            }
        }

        return [$keep, $downloads, $warnings, $linkedKept];
    }

    private function download(ExternalImageData $image): ?UploadedFile
    {
        return $this->downloader->download($image->url, 'image-'.$image->externalId);
    }

    private function downloadVideo(ExternalImageData $video): ?UploadedFile
    {
        return $this->downloader->downloadVideo($video->url, 'video-'.$video->externalId, GalleryMedia::videoMaxBytes());
    }

    /**
     * Stock of the default branch (created when there is none): [branch_id][variant key | "base"] => qty.
     *
     * @param  list<array{external: ExternalVariantData, row: ProductVariantData}>  $variants
     * @param  list<string>  $warnings
     * @return array<int, array<string, int>>
     */
    private function stockCells(ExternalProductData $item, array $variants, array &$warnings): array
    {
        if ($item->unlimitedStock) {
            return [];
        }

        $branch = $this->branches->defaultOrCreate();

        if ($branch === null) {
            $warnings[] = $this->text('catalog::imports.messages.no_branch');

            return [];
        }

        if ($variants === []) {
            return [(int) $branch->id => ['base' => $item->totalStock()]];
        }

        $cells = [];

        foreach ($variants as $variant) {
            $cells[$variant['row']->key] = max(0, $variant['external']->stock);
        }

        return [(int) $branch->id => $cells];
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  list<array{external: ExternalVariantData, row: ProductVariantData}>  $variants
     * @param  list<int>  $keepMediaIds
     * @param  list<UploadedFile>  $newImages
     * @param  array<int, array<string, int>>  $stock
     */
    private function productData(?Product $product, ExternalProductData $item, array $categoryIds, array $variants, array $keepMediaIds, array $newImages, array $stock): ProductData
    {
        // New imports are published (visible to stores as "New products"); an update keeps
        // the status the product has, so an admin's change is never undone (#112).
        $status = ProductStatus::Published;
        $compareAt = null;
        $kept = [
            'slug' => null,
            'cost' => $item->priceMinor,
            'delivery' => $item->requiresShipping ? DeliveryScope::AllZones : DeliveryScope::Digital,
            'countries' => CountryAvailability::All,
            'scope' => AssignmentScope::All,
            'minStock' => 0,
            'countryIds' => [],
            'categoryIds' => [],
            'labelIds' => [],
            'tenantIds' => [],
            'badges' => [],
        ];

        if ($product !== null) {
            $product->loadMissing(['categories', 'labels', 'assignedTenants']);
            $status = $product->status;
            $compareAt = $product->sale_price !== null ? $product->base_price->minor : null;
            $kept = [
                'slug' => $product->slug,
                'cost' => $product->cost_price !== null ? $product->cost_price->minor : $item->priceMinor,
                'delivery' => $product->delivery_scope,
                'countries' => CountryAvailability::fromIds($product->allowed_country_ids),
                'scope' => $product->assignment_scope,
                'minStock' => $product->min_stock,
                'countryIds' => array_map('intval', $product->allowed_country_ids ?? []),
                'categoryIds' => array_map('intval', $product->categories->pluck('id')->all()),
                'labelIds' => array_map('intval', $product->labels->pluck('id')->all()),
                'tenantIds' => array_map('intval', $product->assignedTenants->pluck('id')->all()),
                'badges' => $this->badgeTypes($product),
            ];
        }

        return new ProductData(
            name: $item->name,
            sku: $item->sku,
            slug: $kept['slug'],
            status: $status,
            priceMinor: $item->priceMinor,
            compareAtMinor: $compareAt !== null && $compareAt > $item->priceMinor ? $compareAt : null,
            costMinor: $kept['cost'],
            weightGrams: $item->weightGrams,
            isTaxable: $item->isTaxable,
            requiresShipping: $item->requiresShipping,
            deliveryScope: $kept['delivery'],
            countryAvailability: $kept['countries'],
            assignmentScope: $kept['scope'],
            manageStock: ! $item->unlimitedStock,
            minStock: $kept['minStock'],
            summary: $item->summary !== [] ? $item->summary : $this->translations($product, 'summary'),
            description: $item->description !== [] ? $item->description : $this->translations($product, 'description'),
            metaTitle: $this->translations($product, 'meta_title'),
            metaDescription: $item->metaDescription !== [] ? $item->metaDescription : $this->translations($product, 'meta_description'),
            metaKeywords: $item->metaKeywords !== [] ? $item->metaKeywords : $this->translations($product, 'meta_keywords'),
            countryIds: $kept['countryIds'],
            categoryIds: array_values(array_unique([...$categoryIds, ...$kept['categoryIds']])),
            labelIds: $kept['labelIds'],
            badges: $kept['badges'],
            tenantIds: $kept['tenantIds'],
            variants: array_column($variants, 'row'),
            keepMediaIds: $keepMediaIds,
            newImages: $newImages,
            stock: $stock,
        );
    }

    /**
     * @return array<string, string>
     */
    private function translations(?Product $product, string $field): array
    {
        $values = $product?->getTranslations($field) ?? [];

        return array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function text(string $key, array $params = []): string
    {
        $text = __($key, $params);

        return is_string($text) ? $text : $key;
    }

    /**
     * @return list<string>
     */
    private function badgeTypes(Product $product): array
    {
        $types = [];

        foreach (BadgeType::cases() as $type) {
            if (in_array($product->id, $this->badges->assignedProductIds($this->badgeService->badge($type)), true)) {
                $types[] = $type->value;
            }
        }

        return $types;
    }

    /**
     * Links the saved variants (by option key) and the new gallery rows (in upload
     * order) to their external ids.
     *
     * @param  list<array{external: ExternalVariantData, row: ProductVariantData}>  $variants
     * @param  list<int>  $mediaBefore
     * @param  list<int>  $downloadedExternalIds
     */
    private function linkRecords(ImportDriver $driver, Product $product, ExternalProductData $item, array $variants, array $mediaBefore, array $downloadedExternalIds): void
    {
        $byKey = [];

        foreach ($product->variants()->with('options')->get() as $variant) {
            $byKey[VariantMatrix::key($variant->options->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all())] = $variant->id;
        }

        foreach ($variants as $variant) {
            if (isset($byKey[$variant['row']->key])) {
                $this->imports->saveLink($driver, ImportLinkType::Variant, $variant['external']->externalId, $byKey[$variant['row']->key]);
            }
        }

        $newMedia = $product->gallery()->whereNotIn('id', $mediaBefore)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($downloadedExternalIds as $index => $externalId) {
            if (isset($newMedia[$index])) {
                $this->imports->saveLink($driver, ImportLinkType::Media, $externalId, $newMedia[$index]);
            }
        }
    }
}
