<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Services\AdminTranslation\AdminTranslationRunService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Starts (or resumes, on retry) an admin AI translation run: scans what is still
 * missing and dispatches the batch of TranslateAdminChunk jobs.
 */
class StartAdminTranslationRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $runId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return 'admin-translation-run:'.$this->runId;
    }

    public function handle(AdminTranslationRunService $runs): void
    {
        $runs->start($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        app(AdminTranslationRunService::class)->fail($this->runId, (string) __('ai::admin_translation.errors.crashed'));
    }
}
