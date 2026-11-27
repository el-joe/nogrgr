<?php

namespace App\Modules\Platform\Legacy\Console;

use App\Modules\Platform\Legacy\DTOs\LegacyRunOptions;
use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Services\LegacyMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Old system (multi-vendor) → Nogrgr data migration (P12, docs/plan/data-migration.md).
 * Repeatable and idempotent: ids are mapped in legacy_central_ids / legacy_store_ids.
 *
 *   php artisan legacy:migrate --dry-run
 *   php artisan legacy:migrate --step=catalog --step=tenants --force
 *   php artisan legacy:migrate --tenant=6e70e6e5-49d5-40cf-b66c-675dedf2c8dd --step=orders --force
 */
class LegacyMigrateCommand extends Command
{
    protected $signature = 'legacy:migrate
        {--step=* : Only these steps (localization, plans, settings, gateways, catalog, admins, tenants, promotions, support, affiliates, store_catalog, customers, orders, engagement, store_promotions, finalize)}
        {--tenant=* : Only these old store ids}
        {--dry-run : Read and validate everything, write nothing but the report}
        {--fresh-mapping : Forget previous id mappings (rows are matched by natural keys or created again)}
        {--source=/var/www/multi-vendor : Path of the old project (its .env gives the DB and APP_KEY)}
        {--no-media : Do not copy image files}
        {--report= : Report path (default docs/plan/migration-report.md)}
        {--force : Do not ask for confirmation before writing}';

    protected $description = 'Migrate data from the old multi-vendor system (idempotent; --dry-run writes only the report)';

    public function handle(LegacyMigrationService $service): int
    {
        $steps = [];

        foreach ((array) $this->option('step') as $value) {
            $step = LegacyStep::tryFrom((string) $value);

            if ($step === null) {
                $this->components->error("Unknown step [{$value}].");

                return self::INVALID;
            }

            $steps[] = $step;
        }

        $dryRun = (bool) $this->option('dry-run');
        $target = (string) DB::connection()->getDatabaseName();

        if (! $dryRun && ! $this->option('force') && ! $this->confirm("Write migrated data into [{$target}] and its store databases?")) {
            return self::FAILURE;
        }

        $options = new LegacyRunOptions(
            steps: $steps,
            tenants: array_values(array_map('strval', (array) $this->option('tenant'))),
            dryRun: $dryRun,
            freshMapping: (bool) $this->option('fresh-mapping'),
            copyMedia: ! $this->option('no-media'),
            reportPath: $this->option('report') ?: dirname(base_path()).'/docs/plan/migration-report.md',
        );

        $config = LegacySourceConfig::fromProject((string) $this->option('source'));
        $started = now();
        $report = $service->run($config, $options);

        $rows = [];

        foreach ($report->counts() as $scope => $entities) {
            foreach ($entities as $entity => $metrics) {
                $rows[] = [$scope, $entity, $metrics['read'] ?? 0, $metrics['created'] ?? 0, $metrics['updated'] ?? 0, $metrics['matched'] ?? 0, $metrics['skipped'] ?? 0];
            }
        }

        $this->table(['Scope', 'Entity', 'Read', 'Created', 'Updated', 'Matched', 'Skipped'], $rows);

        $markdown = $report->toMarkdown([
            'Run' => $started->toDateTimeString().' → '.now()->toDateTimeString(),
            'Mode' => $dryRun ? 'dry run (nothing written)' : 'write',
            'Source' => $config->database.' (+ store DBs)',
            'Target' => $target,
            'Steps' => $steps === [] ? 'all' : implode(', ', array_map(static fn (LegacyStep $step): string => $step->value, $steps)),
            'Errors' => (string) $report->errorCount(),
        ]);

        if (@file_put_contents((string) $options->reportPath, $markdown) === false) {
            $this->components->warn('Report could not be written to '.$options->reportPath);
        } else {
            $this->components->info('Report: '.$options->reportPath);
        }

        $this->components->info(($dryRun ? 'Dry run' : 'Run').' finished with '.$report->errorCount().' error(s) and '.count($report->issues()).' distinct finding(s).');

        return self::SUCCESS;
    }
}
