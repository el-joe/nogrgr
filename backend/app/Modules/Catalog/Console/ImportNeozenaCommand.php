<?php

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\DTOs\Imports\StartImportData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Services\Imports\CatalogImportService;
use DomainException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * CLI front of the Neozena catalog import (ADM-013): the same CatalogImportService runs
 * as Admin › Catalog › Import, products land only in the central catalog (#65). Queued
 * by default; `--sync` imports the pages in this process with a progress bar.
 */
class ImportNeozenaCommand extends Command
{
    public const FAILURES_SHOWN = 20;

    protected $signature = 'catalog:import-neozena
        {--pages= : Import at most this many pages}
        {--from-page=1 : First source page to import}
        {--sync : Import the pages in this process (progress bar) instead of queuing them}
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
            $this->components->error('Neozena is not configured. Enable it and set the API base URL in Admin › Integrations › Neozena, then run this command again.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($imports, $overview, $fromPage, $pages);
        }

        if ($resume !== null) {
            return $this->resume($imports, $resume);
        }

        $sync = (bool) $this->option('sync');
        $updateExisting = (bool) $this->option('update-existing');
        $scope = ($pages !== null ? $pages.' page(s)' : 'every page').' from page '.$fromPage;

        if (! $this->option('force') && ! $this->confirm('Import '.$scope.' of '.($overview['endpoint'] ?? 'Neozena').' into the central catalog'.($updateExisting ? ', refreshing products imported before' : '').'?', false)) {
            $this->components->warn('Import not started.');

            return self::FAILURE;
        }

        try {
            $run = $imports->start(new StartImportData($driver, $updateExisting, $pages, $fromPage, $sync), null);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Run #'.$run->id.' started ('.$scope.', '.($sync ? 'inline' : 'queued').').');

        if ($sync) {
            return $this->runInline($imports, $run->id);
        }

        $run = $imports->run($run->id) ?? $run;

        if ($run->status->isActive()) {
            $this->line('  Pages run on the "'.CatalogImportService::QUEUE.'" queue. Follow it in Admin › Catalog › Import.');

            return self::SUCCESS;
        }

        return $this->summary($imports, $run);
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

        try {
            if (! $run->isInline()) {
                $imports->resume($run);
                $this->components->info('Run #'.$run->id.' resumed: its queued pages continue on the "'.CatalogImportService::QUEUE.'" queue.');

                return self::SUCCESS;
            }

            $imports->reopenInline($run);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Run #'.$run->id.' continues from page '.($run->fromPage() + $run->pages_done).'.');

        return $this->runInline($imports, $run->id);
    }

    private function runInline(CatalogImportService $imports, int $runId): int
    {
        if (extension_loaded('pcntl') && defined('SIGINT') && defined('SIGTERM')) {
            $this->trap([SIGINT, SIGTERM], function (): void {
                $this->stopping = true;
            });
        }

        $run = $imports->runInline(
            $runId,
            function (int $page, CatalogImportRun $run): void {
                if ($this->bar === null) {
                    $this->bar = $this->output->createProgressBar((int) $run->total_pages);
                    $this->bar->setFormat(' %current%/%max% pages [%bar%] %percent:3s%% · %message%');
                }

                $this->bar->setMessage(sprintf('%d created, %d updated, %d skipped, %d failed', $run->created_count, $run->updated_count, $run->skipped_count, $run->error_count));
                $this->bar->setProgress(min($run->pages_done, (int) $run->total_pages));
            },
            fn (): bool => $this->stopping,
        );

        if ($this->bar !== null) {
            $this->bar->finish();
            $this->newLine(2);
        }

        if ($run === null) {
            return self::FAILURE;
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
