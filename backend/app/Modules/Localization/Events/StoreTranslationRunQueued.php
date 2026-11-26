<?php

namespace App\Modules\Localization\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A paid store translation run is waiting in the queue (after payment, or a retry of a
 * failed run). The P10 Ai module listens, translates the whole store into
 * `languageCode` (decision #44 scope + static texts), reports progress on the run and
 * finally calls LanguageService::activateForTenant().
 */
final class StoreTranslationRunQueued
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly int $tenantId,
        public readonly string $languageCode,
    ) {}
}
