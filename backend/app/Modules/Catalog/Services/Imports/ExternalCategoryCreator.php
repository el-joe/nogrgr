<?php

namespace App\Modules\Catalog\Services\Imports;

use App\Modules\Catalog\DTOs\CategoryData;
use App\Modules\Catalog\DTOs\Imports\ExternalCategoryData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Imports\ExternalCatalogManager;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Services\CategoryService;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Creates the central category of a source category nobody mapped yet (#112, client
 * feedback; the old importer did the same): details from the source (names, slug,
 * description, image), the parent chain first (at most CategoryService::MAX_DEPTH
 * levels), an active category through CategoryService (thumbnail conversions queued),
 * then the mapping in catalog_import_category_maps. A manual mapping always wins; a
 * lock per source category keeps parallel page jobs from creating it twice.
 */
class ExternalCategoryCreator
{
    public const LOCK_SECONDS = 120;

    public const LOCK_WAIT_SECONDS = 60;

    public function __construct(
        private readonly CatalogImportRepositoryContract $imports,
        private readonly CategoryRepositoryContract $categories,
        private readonly CategoryService $categoryService,
        private readonly ExternalCatalogManager $drivers,
        private readonly ExternalImageDownloader $downloader,
    ) {}

    /**
     * Local category id of the source category (mapped, adopted or created).
     *
     * @throws DomainException when it cannot be created
     */
    public function resolve(ImportDriver $driver, ExternalCategoryData $category, int $depth = 1): int
    {
        $mapped = $this->imports->categoryMapping($driver, [$category->externalId])[$category->externalId] ?? null;

        if ($mapped !== null) {
            return $mapped;
        }

        try {
            return Cache::lock('catalog-import-category:'.$driver->value.':'.$category->externalId, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): int => $this->create($driver, $category, $depth));
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new DomainException(__('catalog::imports.errors.category_create_failed', ['name' => $category->displayName(), 'detail' => mb_substr($exception->getMessage(), 0, 200)]), 0, $exception);
        }
    }

    private function create(ImportDriver $driver, ExternalCategoryData $reference, int $depth): int
    {
        // Another job may have created it while this one waited for the lock.
        $mapped = $this->imports->categoryMapping($driver, [$reference->externalId])[$reference->externalId] ?? null;

        if ($mapped !== null) {
            return $mapped;
        }

        /** @var Category|null $existing */
        $existing = $this->categories->query()->where('external_source', $driver->value)->where('external_id', $reference->externalId)->first();

        if ($existing !== null) {
            $this->imports->mapCategory($driver, $reference, (int) $existing->id);

            return (int) $existing->id;
        }

        $detail = $this->drivers->driver($driver)->fetchCategory($reference->externalId);
        $category = $detail !== null ? $this->merge($reference, $detail) : $reference;
        $parentId = null;

        if ($category->parentExternalId !== null && $category->parentExternalId !== $category->externalId && $depth < CategoryService::MAX_DEPTH) {
            try {
                $parentId = $this->resolve($driver, new ExternalCategoryData($category->parentExternalId, ['en' => '#'.$category->parentExternalId]), $depth + 1);
            } catch (DomainException) {
                $parentId = null;
            }
        }

        $thumbnail = $category->imageUrl !== null ? $this->downloader->download($category->imageUrl, 'category-'.$category->externalId) : null;

        try {
            $local = $this->categoryService->create(new CategoryData(
                name: $category->name,
                slug: $category->slug,
                parentId: $parentId,
                status: RecordStatus::Active,
                isFeatured: false,
                showInMenu: true,
                description: $category->description,
                thumbnail: $thumbnail,
            ));
        } finally {
            if ($thumbnail !== null) {
                @unlink($thumbnail->getRealPath());
            }
        }

        $this->categories->update($local, ['external_source' => $driver->value, 'external_id' => $category->externalId]);
        $this->imports->mapCategory($driver, $category, (int) $local->id);

        return (int) $local->id;
    }

    /**
     * The detail record of the source, with the product's reference filling its gaps.
     */
    private function merge(ExternalCategoryData $reference, ExternalCategoryData $detail): ExternalCategoryData
    {
        $placeholder = $detail->name === ['en' => '#'.$detail->externalId];

        return new ExternalCategoryData(
            $reference->externalId,
            $placeholder ? $reference->name : $detail->name,
            $detail->parentExternalId ?? $reference->parentExternalId,
            $detail->slug ?? $reference->slug,
            $detail->description !== [] ? $detail->description : $reference->description,
            $detail->imageUrl ?? $reference->imageUrl,
        );
    }
}
