<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Services\StoreTranslation\StoreTranslationRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After the last chunk: completes the run and switches the language on, or marks it
 * failed (decision #45).
 */
class FinishStoreTranslationRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $runId) {}

    public function handle(StoreTranslationRunService $runs): void
    {
        $runs->finish($this->runId);
    }
}
