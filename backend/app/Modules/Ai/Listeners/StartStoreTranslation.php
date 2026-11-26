<?php

namespace App\Modules\Ai\Listeners;

use App\Modules\Ai\Jobs\StartStoreTranslationRun;
use App\Modules\Localization\Events\StoreTranslationRunQueued;

/**
 * A bought language's translation run was queued (payment or retry): start it.
 */
class StartStoreTranslation
{
    public function handle(StoreTranslationRunQueued $event): void
    {
        StartStoreTranslationRun::dispatch($event->runId);
    }
}
