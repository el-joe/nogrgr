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
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Inventory\Services\BranchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Upserts one external product into the central catalog through ProductService (same
 * path as the admin editor: variants, gallery + queued conversions, stock, events).
 * Idempotent: the product is found by its external id (then by SKU), variants and
 * gallery images through catalog_import_links, attributes / options by
 * external_source + external_id. Admin-only data (labels, badges, tenants, countries,
 * extra categories and images) is kept on update. Unchanged payloads are skipped.
 */
class ExternalProductImporter
{
    public const IMAGE_TIMEOUT_SECONDS = 30;

    public const IMAGE_MAX_BYTES = 10_485_760;

    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepositoryContract $products,
        private readonly AttributeRepositoryContract $attributes,
        private readonly ProductBadgeRepositoryContract $badges,
        private readonly BadgeService $badgeService,
        private readonly BranchService $branches,
        private readonly CatalogImportRepositoryContract $imports,
    ) {}

    public function import(CatalogImportRun $run, ExternalProductData $item): ImportItemOutcome
    {
        $driver = $run->driver;
        $images = count($item->images);
        $skip = static fn (string $message, ?int $productId = null): ImportItemOutcome => new ImportItemOutcome(ImportLogResult::Skipped, $message, $productId, $images, $images);

        $this->imports->discoverCategories($driver, $item->categories);
        $mapping = $this->imports->categoryMapping($driver, array_map(static fn ($category): int => $category->externalId, $item->categories));
        $unmapped = array_values(array_filter($item->categories, static fn ($category): bool => ($mapping[$category->externalId] ?? null) === null));

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
        $variants = $this->variantRows($driver, $product, $item);
        [$keepMediaIds, $downloads, $warnings, $linkedImages] = $this->gallery($driver, $product, $item);
        $stock = $this->stockCells($item, $variants, $warnings);

        try {
            $data = $this->productData($product, $item, array_values(array_filter($mapping)), $variants, $keepMediaIds, array_column($downloads, 'file'), $stock);
            $mediaBefore = $product !== null ? array_map('intval', $product->media()->pluck('id')->all()) : [];
            $product = $product === null ? $this->productService->create($data) : $this->productService->update($product, $data);
        } finally {
            foreach ($downloads as $download) {
                @unlink($download['file']->getRealPath());
            }
        }

        $this->products->update($product, ['external_source' => $driver->value, 'external_id' => $item->externalId]);
        $this->linkRecords($driver, $product, $item, $variants, $mediaBefore, array_column($downloads, 'external_id'));
        $this->imports->saveLink($driver, ImportLinkType::Product, $item->externalId, $product->id, $item->checksum());

        $summary = __('catalog::imports.messages.summary', ['variants' => count($variants), 'images' => $images]);
        $newPrice = $product->refresh()->costBasis();

        if (! $isNew && $previousPrice !== null && ! $previousPrice->equals($newPrice)) {
            $summary = __('catalog::imports.messages.price_changed', ['from' => $previousPrice->format('en'), 'to' => $newPrice->format('en')]);
        }

        return new ImportItemOutcome(
            $isNew ? ImportLogResult::Created : ImportLogResult::Updated,
            $summary,
            $product->id,
            $images,
            $linkedImages + count($downloads),
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
    private function variantRows(ImportDriver $driver, ?Product $product, ExternalProductData $item): array
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
            $optionIds = array_map(fn (ExternalOptionData $option): int => $this->optionId($driver, $option), $variant->options);
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
                    isActive: $variant->isActive,
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
     * Local option of an external attribute value (attribute and option created on first sight).
     */
    private function optionId(ImportDriver $driver, ExternalOptionData $option): int
    {
        /** @var Attribute|null $attribute */
        $attribute = $this->attributes->query()->withTrashed()->where('external_source', $driver->value)->where('external_id', $option->attributeExternalId)->first();

        if ($attribute === null) {
            /** @var Attribute $attribute */
            $attribute = $this->attributes->create([
                'name' => $option->attributeName,
                'display_type' => AttributeDisplayType::Text,
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

        if ($local === null) {
            $local = $this->attributes->createOption($attribute, [
                'name' => $option->name,
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
     * Kept gallery ids (imported images still in the source first, then admin uploads),
     * the new images to download, warnings and the number of images already present.
     *
     * @return array{0: list<int>, 1: list<array{external_id: int, file: UploadedFile}>, 2: list<string>, 3: int}
     */
    private function gallery(ImportDriver $driver, ?Product $product, ExternalProductData $item): array
    {
        $own = $product !== null ? array_map('intval', $product->media()->orderBy('sort_order')->pluck('id')->all()) : [];
        $linkedByLocal = $this->imports->externalIdsFor($driver, ImportLinkType::Media, $own);
        $localByExternal = array_flip($linkedByLocal);
        $keep = [];
        $downloads = [];
        $warnings = [];

        foreach ($item->images as $image) {
            if (isset($localByExternal[$image->externalId])) {
                $keep[] = $localByExternal[$image->externalId];

                continue;
            }

            $file = $this->download($image);

            if ($file === null) {
                $warnings[] = $this->text('catalog::imports.messages.image_failed', ['url' => mb_substr($image->url, 0, 200)]);

                continue;
            }

            $downloads[] = ['external_id' => $image->externalId, 'file' => $file];
        }

        $linkedKept = count($keep);

        foreach ($own as $mediaId) {
            if (! isset($linkedByLocal[$mediaId])) {
                $keep[] = $mediaId;
            }
        }

        return [$keep, $downloads, $warnings, $linkedKept];
    }

    private function download(ExternalImageData $image): ?UploadedFile
    {
        try {
            $response = Http::timeout(self::IMAGE_TIMEOUT_SECONDS)->retry(1, 500, throw: false)->get($image->url);
        } catch (ConnectionException) {
            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $body = $response->body();

        if (! $response->successful() || ! str_starts_with($mime, 'image/') || $body === '' || strlen($body) > self::IMAGE_MAX_BYTES) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'catalog-import-');

        if ($path === false || file_put_contents($path, $body) === false) {
            return null;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'jpg',
        };

        return new UploadedFile($path, 'image-'.$image->externalId.'.'.$extension, $mime, null, true);
    }

    /**
     * Stock of the first active branch: [branch_id][variant key | "base"] => qty.
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

        $branch = $this->branches->active()->first();

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
        $status = $item->isActive ? ProductStatus::Published : ProductStatus::Draft;
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
            $status = $product->status === ProductStatus::Archived ? ProductStatus::Archived : $status;
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

        $newMedia = $product->media()->whereNotIn('id', $mediaBefore)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($downloadedExternalIds as $index => $externalId) {
            if (isset($newMedia[$index])) {
                $this->imports->saveLink($driver, ImportLinkType::Media, $externalId, $newMedia[$index]);
            }
        }
    }
}
