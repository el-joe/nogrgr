<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\DTOs\Imports\ExternalCategoryData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportLinkType;
use App\Modules\Catalog\Enums\ImportLogResult;
use App\Modules\Catalog\Models\CatalogImportCategoryMap;
use App\Modules\Catalog\Models\CatalogImportLog;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * External catalog import (ADM-013): runs, their log, external => local links and the
 * source category mapping.
 *
 * @extends RepositoryContract<CatalogImportRun>
 */
interface CatalogImportRepositoryContract extends RepositoryContract
{
    public function activeRun(ImportDriver $driver): ?CatalogImportRun;

    public function latestRun(ImportDriver $driver): ?CatalogImportRun;

    public function lastCompletedRun(ImportDriver $driver): ?CatalogImportRun;

    /**
     * Atomic counter increments: column => amount.
     *
     * @param  array<string, int>  $amounts
     */
    public function increment(CatalogImportRun $run, array $amounts): void;

    /**
     * @param  array{external_id?: int|null, sku?: string|null, item_name?: string|null, product_id?: int|null}  $item
     */
    public function log(CatalogImportRun $run, ImportLogResult $result, string $message, array $item = []): CatalogImportLog;

    /**
     * @return Builder<CatalogImportLog>
     */
    public function logsQuery(CatalogImportRun $run): Builder;

    /**
     * @return Builder<CatalogImportRun>
     */
    public function historyQuery(): Builder;

    public function linkedId(ImportDriver $driver, ImportLinkType $type, int $externalId): ?int;

    public function checksum(ImportDriver $driver, ImportLinkType $type, int $externalId): ?string;

    /**
     * @param  list<int>  $externalIds
     * @return array<int, int> external id => local id
     */
    public function linkedIds(ImportDriver $driver, ImportLinkType $type, array $externalIds): array;

    /**
     * @param  list<int>  $localIds
     * @return array<int, int> local id => external id
     */
    public function externalIdsFor(ImportDriver $driver, ImportLinkType $type, array $localIds): array;

    public function saveLink(ImportDriver $driver, ImportLinkType $type, int $externalId, int $localId, ?string $checksum = null): void;

    /**
     * Upserts the names of the given source categories (keeps their mapping); a new one
     * is pre-mapped to the central category imported with the same external id, if any.
     *
     * @param  list<ExternalCategoryData>  $categories
     */
    public function discoverCategories(ImportDriver $driver, array $categories): void;

    /**
     * @param  list<int>  $externalIds
     * @return array<int, int|null> external id => central category id (null = unmapped)
     */
    public function categoryMapping(ImportDriver $driver, array $externalIds): array;

    /**
     * @return Builder<CatalogImportCategoryMap>
     */
    public function categoryMapsQuery(ImportDriver $driver): Builder;

    public function unmappedCount(ImportDriver $driver): int;

    public function categoryMapCount(ImportDriver $driver): int;

    /**
     * @param  array<int, int|null>  $mappings  map row id => central category id
     */
    public function saveCategoryMappings(ImportDriver $driver, array $mappings): void;
}
