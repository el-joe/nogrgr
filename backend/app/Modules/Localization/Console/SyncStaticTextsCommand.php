<?php

namespace App\Modules\Localization\Console;

use App\Modules\Localization\Services\StaticTexts\StaticTextDispatcher;
use Illuminate\Console\Command;

/**
 * Run after every deploy: queue a static text sync for every provisioned store (or
 * the given ids) so new base keys reach them without overwriting edited / locked texts.
 */
class SyncStaticTextsCommand extends Command
{
    protected $signature = 'localization:sync-static-texts {--tenant=* : Only these store ids}';

    protected $description = 'Queue the storefront static texts sync (new base keys) for every store';

    public function handle(StaticTextDispatcher $dispatcher): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('tenant'))));
        $count = $dispatcher->dispatchAll($ids === [] ? null : $ids);

        $this->components->info("Queued the static texts sync for {$count} store(s).");

        return self::SUCCESS;
    }
}
