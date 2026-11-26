<?php

namespace App\Modules\Ai\Console;

use App\Modules\Ai\Services\StoreTranslation\DeltaTranslationService;
use Illuminate\Console\Command;

/**
 * Weekly: re-queue a delta translation run per active store × bought language (only
 * new / changed content is sent to the paid provider).
 */
class TranslateDeltasCommand extends Command
{
    protected $signature = 'ai:translate-deltas';

    protected $description = 'Queue weekly delta AI translation runs for every store language bought';

    public function handle(DeltaTranslationService $deltas): int
    {
        $runs = $deltas->queueDue();
        $this->info('Queued '.count($runs).' delta translation run(s).');

        return self::SUCCESS;
    }
}
