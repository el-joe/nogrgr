<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\StoreTranslation\StoreTranslationRunService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One chunk (≤ 20 items) of one section of a paid store translation run. Idempotent:
 * translated items with an unchanged source are skipped, nothing is written before the
 * provider answered. Rate limits / outages release the job with backoff; on the last
 * attempt the chunk's items are recorded as failed (the vendor's retry resumes them).
 */
class TranslateStoreChunk implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 4;

    public int $timeout = 300;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        public readonly int $runId,
        public readonly StoreTranslationSection $section,
        public readonly array $keys,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(StoreTranslationRunService $runs): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $lastAttempt = $this->job === null
            || $this->job->getConnectionName() === 'sync'
            || $this->attempts() >= $this->tries;

        try {
            $runs->translateChunk($this->runId, $this->section, $this->keys, $lastAttempt);
        } catch (AiRequestException) {
            $this->release($this->backoff()[max(0, min($this->attempts() - 1, 2))]);
        }
    }
}
