<?php

namespace App\Modules\Platform\Legacy\Console;

use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use App\Modules\Platform\Legacy\Services\LegacyReconciliationService;
use App\Shared\Money\Money;
use Illuminate\Console\Command;

/**
 * Finance reconciliation old system ↔ Nogrgr (P12 gate, decision #113): per migrated
 * store and platform totals of orders, profits, refunds, subscription payments,
 * settlements and payouts (+ ledger). Read only. Writes / replaces the "Finance
 * reconciliation" section of the migration report and fails when a difference exists.
 *
 *   php artisan legacy:reconcile
 */
class LegacyReconcileCommand extends Command
{
    private const HEADING = '## Finance reconciliation';

    protected $signature = 'legacy:reconcile
        {--source=/var/www/multi-vendor : Path of the old project (its .env gives the DB)}
        {--report= : Report path (default docs/plan/migration-report.md)}
        {--no-report : Only print the table}';

    protected $description = 'Compare old vs migrated finance totals (orders, profits, refunds, payments, settlements, payouts, ledger)';

    public function handle(LegacyReconciliationService $service): int
    {
        $result = $service->run(LegacySourceConfig::fromProject((string) $this->option('source')));
        $money = static fn (?int $value, bool $isMoney): string => $value === null ? 'n/a' : ($isMoney ? Money::ofMinor($value)->format('en') : (string) $value);

        $this->table(['Scope', 'Metric', 'Old', 'New', 'Diff'], array_map(static fn (array $line): array => [
            $line['scope'],
            $line['metric'],
            $money($line['old'], $line['money']),
            $money($line['new'], $line['money']),
            $line['old'] === null ? 'n/a' : $money($line['new'] - $line['old'], $line['money']),
        ], $result->lines()));

        if (! $this->option('no-report')) {
            $path = (string) ($this->option('report') ?: dirname(base_path()).'/docs/plan/migration-report.md');
            $current = is_file($path) ? (string) file_get_contents($path) : '';
            $section = implode("\n", $result->markdown())."\n";
            $start = strpos($current, self::HEADING);

            if ($start === false) {
                $updated = rtrim($current)."\n\n".$section;
            } else {
                $next = strpos($current, "\n## ", $start + strlen(self::HEADING));
                $updated = substr($current, 0, $start).$section.($next === false ? '' : substr($current, $next));
            }

            if (@file_put_contents($path, $updated) === false) {
                $this->components->warn('Report could not be written to '.$path);
            } else {
                $this->components->info('Report: '.$path);
            }
        }

        if ($result->differences() > 0) {
            $this->components->error($result->differences().' difference(s) between the old and the new system.');

            return self::FAILURE;
        }

        $this->components->info('Finance reconciliation: 0 differences.');

        return self::SUCCESS;
    }
}
