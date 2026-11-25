<?php

namespace App\Modules\Catalog\Imports\Contracts;

use App\Modules\Catalog\DTOs\Imports\ExternalCatalogPage;
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
     * Credentials saved and the Integrations card enabled.
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
}
