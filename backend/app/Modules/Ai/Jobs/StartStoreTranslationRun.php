<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Services\StoreTranslation\StoreTranslationRunService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Starts (or resumes, on retry) a paid store translation run: counts the store's items
 * and dispatches the batch of TranslateStoreChunk jobs (decision #44).
 */
class StartStoreTranslationRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

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
        return 'store-translation-run:'.$this->runId;
    }

    public function handle(StoreTranslationRunService $runs): void
    {
        $runs->start($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        app(StoreTranslationRunService::class)->fail($this->runId, (string) __('ai::store_translation.errors.crashed'));
    }
}
