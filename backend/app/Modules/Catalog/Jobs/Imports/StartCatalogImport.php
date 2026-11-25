<?php

namespace App\Modules\Catalog\Jobs\Imports;

use App\Modules\Catalog\Services\Imports\CatalogImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads the first page of the source for its totals and queues the page batch.
 */
class StartCatalogImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $runId) {}

    public function handle(CatalogImportService $imports): void
    {
        $imports->begin($this->runId);
    }
}
