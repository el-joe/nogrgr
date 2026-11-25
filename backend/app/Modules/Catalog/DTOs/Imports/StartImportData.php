<?php

namespace App\Modules\Catalog\DTOs\Imports;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Shared\DTOs\BaseDTO;

/**
 * "Run import": which driver, whether already imported products are refreshed, and an
 * optional page limit (partial sync).
 */
final class StartImportData extends BaseDTO
{
    public function __construct(
        public readonly ImportDriver $driver,
        public readonly bool $updateExisting = true,
        public readonly ?int $maxPages = null,
    ) {}
}
