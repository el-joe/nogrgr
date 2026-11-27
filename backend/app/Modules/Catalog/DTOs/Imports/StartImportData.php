<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Shared\DTOs\BaseDTO;

/**
 * "Run import": which driver, whether already imported products are refreshed, an
 * optional page limit / first page (partial sync) and whether the pages run inline in
 * the calling process (`catalog:import-neozena --sync`) instead of as a queued batch.
 */
final class StartImportData extends BaseDTO
{
    public function __construct(
        public readonly ImportDriver $driver,
        public readonly bool $updateExisting = true,
        public readonly ?int $maxPages = null,
        public readonly int $fromPage = 1,
        public readonly bool $inline = false,
    ) {}
}
