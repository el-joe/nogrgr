<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Services\AdminTranslation\AdminTranslationRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After the last chunk: completes the admin run or marks it failed.
 */
class FinishAdminTranslationRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $runId) {}

    public function handle(AdminTranslationRunService $runs): void
    {
        $runs->finish($this->runId);
    }
}
