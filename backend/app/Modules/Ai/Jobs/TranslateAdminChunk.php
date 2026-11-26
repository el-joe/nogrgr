<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\AdminTranslation\AdminTranslationRunService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One chunk (≤ 20 items) of one section of an admin AI translation run. Idempotent:
 * items filled meanwhile are skipped, nothing is written before the provider answered.
 * Rate limits / outages release the job with backoff; on the last attempt the chunk's
 * items count as failed (the admin's retry resumes them).
 */
class TranslateAdminChunk implements ShouldQueue
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
        public readonly string $section,
        public readonly array $keys,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(AdminTranslationRunService $runs): void
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
