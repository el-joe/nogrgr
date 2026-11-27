<?php

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\DTOs\Imports\StartImportData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Imports\Drivers\NeozenaDriver;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Services\Imports\CatalogImportService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * CLI front of the Neozena catalog import (ADM-013): the same CatalogImportService runs
 * as Admin › Catalog › Import, products land only in the central catalog (#65). Every
 * page is a queued ImportCatalogPage job of the run's batch (a queue worker must run);
 * the command then watches the run row (progress bar, final counts) unless `--no-wait`.
 * Neozena's public API works without a token or saved settings (#112).
 */
class ImportNeozenaCommand extends Command
{
    public const FAILURES_SHOWN = 20;

    public const POLL_SECONDS = 2;

    /**
     * Still "queued" after this long: most likely no queue worker is running.
     */
    public const NO_WORKER_HINT_SECONDS = 30;

    public const WORKER_HINT = 'Pages are imported by queue jobs: keep a worker running, e.g. `php artisan queue:work --queue='.CatalogImportService::QUEUE.'` (Supervisor / Horizon in production).';

    protected $signature = 'catalog:import-neozena
        {--pages= : Import at most this many pages}
        {--from-page=1 : First source page to import}
        {--no-wait : Queue the run and exit instead of showing its progress}
        {--no-auto-create : Skip products of unmapped categories / unknown attributes instead of creating them}
        {--dry-run : Show what the source returns and the planned pages; write nothing}
        {--resume= : Continue an earlier run by its id}
        {--update-existing : Refresh prices, stock and images of products imported before}
        {--force : Start without asking for confirmation}';

    protected $description = 'Import (or refresh) the Neozena product catalog into the central catalog';

    private bool $stopping = false;

    private ?ProgressBar $bar = null;

    public function handle(CatalogImportService $imports): int
    {
        $driver = ImportDriver::Neozena;
        $pages = $this->positiveOption('pages');
        $fromPage = $this->positiveOption('from-page') ?? 1;
        $resume = $this->positiveOption('resume');

        if ($pages === false || $fromPage === false || $resume === false) {
            return self::INVALID;
        }

        $overview = $imports->overview($driver);

        if (! $overview['configured']) {
            $this->components->error('Neozena has no API base URL. Set it in Admin › Integrations › Neozena (the default is '.NeozenaDriver::DEFAULT_BASE_URL.'), then run this command again.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($imports, $overview, $fromPage, $pages);
        }

        if ($resume !== null) {
            return $this->resume($imports, $resume);
        }

        $updateExisting = (bool) $this->option('update-existing');
        $scope = ($pages !== null ? $pages.' page(s)' : 'every page').' from page '.$fromPage;

        if (! $this->option('force') && ! $this->confirm('Import '.$scope.' of '.($overview['endpoint'] ?? 'Neozena').' into the central catalog'.($updateExisting ? ', refreshing products imported before' : '').'?', false)) {
            $this->components->warn('Import not started.');

            return self::FAILURE;
        }

        try {
            $run = $imports->start(new StartImportData($driver, $updateExisting, $pages, $fromPage, ! $this->option('no-auto-create')), null);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Run #'.$run->id.' queued ('.$scope.').');

        return $this->follow($imports, $run->id);
    }

    /**
     * @param  array{configured: bool, endpoint: string|null, mapped: int, mappings: int, unmapped: int}  $overview
     */
    private function dryRun(CatalogImportService $imports, array $overview, int $fromPage, ?int $pages): int
    {
        try {
            $preview = $imports->preview(ImportDriver::Neozena, $fromPage, discover: false);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $last = $preview['pages'];
        $planned = $fromPage > $last ? 0 : ($pages !== null ? min($pages, $last - $fromPage + 1) : $last - $fromPage + 1);

        $this->components->info('Dry run: nothing is written.');
        $this->table(['', ''], [
            ['Endpoint', $overview['endpoint'] ?? '—'],
            ['Products in the source', $preview['total']],
            ['Source pages', $last],
            ['Pages to import', $planned > 0 ? $planned.' (page '.$fromPage.' to '.($fromPage + $planned - 1).')' : 'none: page '.$fromPage.' is past the last page'],
            ['Unreadable items on page '.$fromPage, $preview['invalid']],
            ['Mapped categories', $overview['mapped'].' / '.$overview['mappings']],
            ['Unmapped categories (products skipped)', $overview['unmapped']],
            ['Existing products', $this->option('update-existing') ? 'refreshed when changed' : 'skipped'],
        ]);

        if ($preview['items'] !== []) {
            $this->table(['SKU', 'Product', 'Price', 'Variants', 'Images', 'Categories'], array_map(
                static fn (array $item): array => [$item['sku'], $item['name'], $item['price'], $item['variants'], $item['images'], $item['categories']],
                $preview['items'],
            ));
        }

        return $planned > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resume(CatalogImportService $imports, int $runId): int
    {
        $run = $imports->run($runId);

        if ($run === null || $run->driver !== ImportDriver::Neozena) {
            $this->components->error('Neozena import run #'.$runId.' does not exist.');

            return self::FAILURE;
        }

        $left = $run->total_pages !== null ? count($imports->remainingPages($run)) : null;

        try {
            $run = $imports->continueRun($run);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Run #'.$run->id.' resumed on the queue'.($left !== null ? ' ('.$left.' page(s) left)' : '').'.');

        return $this->follow($imports, $run->id);
    }

    /**
     * Exits right away with `--no-wait`, else watches the run until it is no longer active.
     */
    private function follow(CatalogImportService $imports, int $runId): int
    {
        $this->line('  '.self::WORKER_HINT);

        if ($this->option('no-wait')) {
            $this->line('  Follow it in Admin › Catalog › Import, or continue it later with: php artisan catalog:import-neozena --resume='.$runId);

            return self::SUCCESS;
        }

        return $this->watch($imports, $runId);
    }

    /**
     * Polls the run row (the queue worker updates it) with a progress bar until it
     * completes, fails, is cancelled or paused. Ctrl+C only stops watching.
     */
    private function watch(CatalogImportService $imports, int $runId): int
    {
        if (extension_loaded('pcntl') && defined('SIGINT') && defined('SIGTERM')) {
            $this->trap([SIGINT, SIGTERM], function (): void {
                $this->stopping = true;
            });
        }

        $waitingSince = now();
        $hinted = false;

        while (true) {
            $run = $imports->run($runId);

            if ($run === null) {
                return self::FAILURE;
            }

            if ($run->total_pages !== null) {
                $this->bar ??= tap($this->output->createProgressBar((int) $run->total_pages), static function (ProgressBar $bar): void {
                    $bar->setFormat(' %current%/%max% pages [%bar%] %percent:3s%% · %message%');
                });
                $this->bar->setMessage(sprintf('%d created, %d updated, %d skipped, %d failed', $run->created_count, $run->updated_count, $run->skipped_count, $run->error_count));
                $this->bar->setProgress(min($run->pages_done, (int) $run->total_pages));
            }

            if (! $run->status->isActive() || $run->status === ImportStatus::Paused) {
                break;
            }

            if ($this->stopping) {
                $this->bar?->finish();
                $this->newLine(2);
                $this->components->warn('Stopped watching. Run #'.$runId.' goes on in the queue; follow it in Admin › Catalog › Import.');

                return self::SUCCESS;
            }

            if (! $hinted && $run->status === ImportStatus::Queued && $waitingSince->diffInSeconds(now()) >= self::NO_WORKER_HINT_SECONDS) {
                $this->components->warn('Still queued: is a queue worker running? Start one with `php artisan queue:work`.');
                $hinted = true;
            }

            Sleep::for(self::POLL_SECONDS)->seconds();
        }

        if ($this->bar !== null) {
            $this->bar->finish();
            $this->newLine(2);
        }

        return $this->summary($imports, $run);
    }

    private function summary(CatalogImportService $imports, CatalogImportRun $run): int
    {
        $this->table(['Run', 'Status', 'Pages', 'Created', 'Updated', 'Skipped', 'Failed'], [[
            '#'.$run->id,
            $run->status->value,
            $run->pages_done.' / '.(int) $run->total_pages,
            $run->created_count,
            $run->updated_count,
            $run->skipped_count,
            $run->error_count,
        ]]);

        if ($run->failure_message !== null) {
            $this->components->error($run->failure_message);
        }

        $failures = $imports->failures($run, self::FAILURES_SHOWN);

        if ($failures !== []) {
            $this->components->warn('Failures'.($run->error_count > count($failures) ? ' (first '.count($failures).' of '.$run->error_count.')' : '').':');

            foreach ($failures as $failure) {
                $item = trim(implode(' ', array_filter([$failure->sku, $failure->external_id !== null ? '#'.$failure->external_id : null])));
                $this->line('  - '.($item !== '' ? $item.': ' : '').$failure->message);
            }
        }

        if ($run->status === ImportStatus::Paused) {
            $this->components->warn('Paused. Continue with: php artisan catalog:import-neozena --resume='.$run->id);
        }

        return $run->status === ImportStatus::Completed && $run->error_count === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Null when the option is absent, false (after printing the error) when it is not a positive integer.
     */
    private function positiveOption(string $name): int|false|null
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            $this->components->error('--'.$name.' must be a positive whole number.');

            return false;
        }

        return (int) $value;
    }
}
