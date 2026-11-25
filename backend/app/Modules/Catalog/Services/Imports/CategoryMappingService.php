<?php

namespace App\Modules\Catalog\Services\Imports;

use App\Modules\Catalog\DTOs\Imports\CategoryMappingData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Models\CatalogImportCategoryMap;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Modules\Catalog\Services\CategoryService;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Support\Collection;

/**
 * "Map categories" step: source categories seen by imports / previews and the central
 * category each one feeds (unmapped ones make their products skipped).
 */
class CategoryMappingService extends BaseService
{
    public function __construct(
        private readonly CatalogImportRepositoryContract $imports,
        private readonly CategoryService $categories,
    ) {}

    /**
     * @return Collection<int, CatalogImportCategoryMap>
     */
    public function rows(ImportDriver $driver, bool $unmappedOnly = false): Collection
    {
        return $this->imports->categoryMapsQuery($driver)
            ->when($unmappedOnly, static fn ($query) => $query->whereDoesntHave('category'))
            ->orderByRaw('category_id IS NOT NULL')
            ->orderBy('external_id')
            ->get();
    }

    /**
     * Central categories as "Parent › Child" paths.
     *
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return array_map(static fn (array $row): string => $row['path'], $this->categories->flatten());
    }

    public function save(ImportDriver $driver, CategoryMappingData $data): void
    {
        $this->transaction(function () use ($driver, $data): void {
            $known = array_map('intval', $this->imports->categoryMapsQuery($driver)->whereKey(array_keys($data->mappings))->pluck('id')->all());

            if (count($known) !== count($data->mappings)) {
                throw new DomainException(__('catalog::imports.mapping.stale'));
            }

            $this->imports->saveCategoryMappings($driver, $data->mappings);
        }, (new CatalogImportRun)->getConnectionName());
    }
}
