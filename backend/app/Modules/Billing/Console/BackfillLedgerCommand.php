<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Services\Ledger\LedgerBackfillService;
use Illuminate\Console\Command;

/**
 * Posts paid orders, settlements and invoices that predate the ledger (decision #74 d).
 * Orders already posted whose ledger drifted from the formulas get one correcting posting.
 * Idempotent: the live listeners' keys are reused, so it can run any number of times.
 *
 *   php artisan finance:backfill-ledger --dry-run
 *   php artisan finance:backfill-ledger --tenant=12 --tenant=15
 */
class BackfillLedgerCommand extends Command
{
    protected $signature = 'finance:backfill-ledger
        {--tenant=* : Only these store ids}
        {--dry-run : Report what would be posted without writing anything}';

    protected $description = 'Post paid orders, settlements and invoices that predate the finance ledger';

    public function handle(LedgerBackfillService $backfill): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('tenant'))));
        $dryRun = (bool) $this->option('dry-run');

        $report = $backfill->run($ids, $dryRun);

        if ($dryRun) {
            $this->components->warn('Dry run: nothing was written.');
        }

        $this->table(['', $dryRun ? 'Would post' : 'Posted', 'Already in ledger', 'Nothing to post'], [
            ['Orders', $report->ordersPosted, $report->ordersExisting, $report->ordersUnchanged],
            ['Order corrections', $report->ordersCorrected, '-', '-'],
            ['Settlements', $report->settlementsPosted, $report->settlementsExisting, '-'],
            ['Invoices', $report->invoicesPosted, $report->invoicesExisting, '-'],
        ]);

        foreach ($report->failures as $tenantId => $message) {
            $this->components->error("Store {$tenantId}: {$message}");
        }

        $this->components->info(($dryRun ? 'Would post ' : 'Posted ').$report->posted()." transaction(s) for {$report->stores} store(s).");

        return $report->failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
