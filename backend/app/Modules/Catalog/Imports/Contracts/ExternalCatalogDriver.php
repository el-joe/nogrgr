<?php

namespace App\Modules\Catalog\Imports\Contracts;

use App\Modules\Catalog\DTOs\Imports\ExternalCatalogPage;
use App\Modules\Catalog\DTOs\Imports\ExternalCategoryData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Imports\Exceptions\ExternalCatalogException;

/**
 * An external product catalog the importer can read page by page. Drivers own the
 * HTTP details and map every item to ExternalProductData (prices in USD minor units);
 * items they cannot map go to ExternalCatalogPage::$invalid instead of failing the page.
 */
interface ExternalCatalogDriver
{
    public function driver(): ImportDriver;

    /**
     * The source can be read (an API base URL is set; Neozena's default counts).
     */
    public function isConfigured(): bool;

    /**
     * Host shown on the import page (no credentials).
     */
    public function endpoint(): ?string;

    /**
     * @throws ExternalCatalogException when the page cannot be read
     */
    public function fetchPage(int $page): ExternalCatalogPage;

    /**
     * Totals of a page without mapping its items or reading product details (run
     * planning in StartCatalogImport: cheap in time and memory).
     *
     * @return array{last_page: int, total: int, count: int}
     *
     * @throws ExternalCatalogException when the page cannot be read
     */
    public function pageInfo(int $page): array;

    /**
     * One category with its details (names, parent, image); null when the source has none.
     *
     * @throws ExternalCatalogException when the source cannot be read
     */
    public function fetchCategory(int $externalId): ?ExternalCategoryData;
}
