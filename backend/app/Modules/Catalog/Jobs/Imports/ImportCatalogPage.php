<?php

namespace App\Modules\Catalog\Jobs\Imports;

use App\Modules\Catalog\Services\Imports\CatalogImportService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One page of an import run (part of the run's Bus batch). While the run is paused the
 * page is re-added to the batch with a delay (no attempt used); cancelled runs stop.
 */
class ImportCatalogPage implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public const PAUSE_DELAY_SECONDS = 15;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public readonly int $runId, public readonly int $page) {}

    public function handle(CatalogImportService $imports): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! $imports->importPage($this->runId, $this->page)) {
            $this->batch()?->add([(new self($this->runId, $this->page))->delay(self::PAUSE_DELAY_SECONDS)]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(CatalogImportService::class)->pageFailed($this->runId, $this->page, (string) $exception?->getMessage());
    }
}
