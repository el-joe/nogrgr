<?php

namespace App\Modules\Catalog\Services\Imports;

use App\Modules\Catalog\DTOs\Imports\ExternalCatalogPage;
use App\Modules\Catalog\DTOs\Imports\ExternalProductData;
use App\Modules\Catalog\DTOs\Imports\ImportItemOutcome;
use App\Modules\Catalog\DTOs\Imports\StartImportData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportLogResult;
use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Events\CatalogImportFinished;
use App\Modules\Catalog\Imports\Exceptions\ExternalCatalogException;
use App\Modules\Catalog\Imports\ExternalCatalogManager;
use App\Modules\Catalog\Jobs\Imports\ImportCatalogPage;
use App\Modules\Catalog\Jobs\Imports\StartCatalogImport;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Modules\Identity\Models\Admin;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * External catalog import runs (ADM-013): start (queued), page jobs in one Bus batch,
 * per-item upsert + log, pause / resume / cancel, the live progress payload and the
 * finish (completed / failed / cancelled → CatalogImportFinished).
 */
class CatalogImportService extends BaseService
{
    public const QUEUE = 'default';

    /**
     * Items shown by the "Preview" step.
     */
    public const PREVIEW_ITEMS = 5;

    public function __construct(
        private readonly CatalogImportRepositoryContract $imports,
        private readonly ExternalCatalogManager $drivers,
        private readonly ExternalProductImporter $importer,
    ) {}

    /**
     * Everything the import page shows for a driver.
     *
     * @return array{driver: ImportDriver, configured: bool, endpoint: string|null, run: CatalogImportRun|null, lastSync: CatalogImportRun|null, mapped: int, mappings: int, unmapped: int}
     */
    public function overview(ImportDriver $driver): array
    {
        $catalog = $this->drivers->driver($driver);
        $mappings = $this->imports->categoryMapCount($driver);
        $unmapped = $this->imports->unmappedCount($driver);

        return [
            'driver' => $driver,
            'configured' => $catalog->isConfigured(),
            'endpoint' => $catalog->endpoint(),
            'run' => $this->imports->latestRun($driver),
            'lastSync' => $this->imports->lastCompletedRun($driver),
            'mapped' => $mappings - $unmapped,
            'mappings' => $mappings,
            'unmapped' => $unmapped,
        ];
    }

    /**
     * First page of the source: totals, a few mapped items and how many cannot be read.
     *
     * @return array{total: int, pages: int, invalid: int, items: list<array{external_id: int, sku: string, name: string, price: string, variants: int, images: int, categories: string}>}
     */
    public function preview(ImportDriver $driver): array
    {
        $page = $this->fetch($driver, 1);

        foreach ($page->items as $item) {
            $this->imports->discoverCategories($driver, $item->categories);
        }

        return [
            'total' => $page->total,
            'pages' => $page->lastPage,
            'invalid' => count($page->invalid),
            'items' => array_map(static fn (ExternalProductData $item): array => [
                'external_id' => $item->externalId,
                'sku' => $item->sku,
                'name' => $item->displayName(),
                'price' => Money::ofMinor($item->priceMinor)->format(),
                'variants' => count($item->variants),
                'images' => count($item->images),
                'categories' => implode(', ', array_map(static fn ($category): string => $category->displayName(), $item->categories)),
            ], array_slice($page->items, 0, self::PREVIEW_ITEMS)),
        ];
    }

    public function start(StartImportData $data, ?Admin $admin): CatalogImportRun
    {
        if (! $this->drivers->driver($data->driver)->isConfigured()) {
            throw new DomainException(__('catalog::imports.errors.not_configured'));
        }

        $run = $this->transaction(function () use ($data, $admin): CatalogImportRun {
            if ($this->imports->activeRun($data->driver) !== null) {
                throw new DomainException(__('catalog::imports.errors.already_running'));
            }

            /** @var CatalogImportRun $run */
            $run = $this->imports->create([
                'driver' => $data->driver,
                'status' => ImportStatus::Queued,
                'options' => ['update_existing' => $data->updateExisting, 'max_pages' => $data->maxPages],
                'started_by' => $admin?->id,
            ]);

            return $run;
        }, $this->connection());

        StartCatalogImport::dispatch($run->id)->onQueue(self::QUEUE)->afterCommit();

        return $run;
    }

    /**
     * Reads the first page for the totals and queues one batched job per page.
     */
    public function begin(int $runId): void
    {
        $run = $this->run($runId);

        if ($run === null || $run->status !== ImportStatus::Queued) {
            return;
        }

        try {
            $first = $this->fetch($run->driver, 1);
        } catch (DomainException $exception) {
            $this->fail($run, $exception->getMessage());

            return;
        }

        $pages = $run->maxPages() !== null ? min($run->maxPages(), $first->lastPage) : $first->lastPage;
        $perPage = max(1, $first->count());
        $total = $pages >= $first->lastPage ? $first->total : min($first->total, $pages * $perPage);

        $this->imports->update($run, [
            'status' => ImportStatus::Running,
            'total_pages' => $pages,
            'total_items' => $total,
            'started_at' => now(),
        ]);

        $batch = Bus::batch(array_map(static fn (int $page): ImportCatalogPage => new ImportCatalogPage($runId, $page), range(1, $pages)))
            ->name('catalog-import-'.$runId)
            ->onQueue(self::QUEUE)
            ->allowFailures()
            ->finally(static function (Batch $batch) use ($runId): void {
                app(self::class)->finish($runId, $batch->failedJobs >= $batch->totalJobs && $batch->totalJobs > 0);
            })
            ->dispatch();

        $this->imports->query()->whereKey($runId)->whereNull('batch_id')->update(['batch_id' => $batch->id]);
    }

    /**
     * Imports one page. Returns false while the run is paused (the page is retried
     * later); a pause during a page lets the page finish, a cancel stops it.
     */
    public function importPage(int $runId, int $page): bool
    {
        $run = $this->run($runId);

        if ($run === null || ! $run->status->isActive()) {
            return true;
        }

        if ($run->status === ImportStatus::Paused) {
            return false;
        }

        try {
            $result = $this->fetch($run->driver, $page);
        } catch (DomainException $exception) {
            $this->pageFailed($runId, $page, $exception->getMessage());

            return true;
        }

        foreach ($result->invalid as $invalid) {
            $this->record($run, new ImportItemOutcome(ImportLogResult::Error, $invalid['message']), ['external_id' => $invalid['external_id'], 'sku' => $invalid['sku']]);
        }

        foreach ($result->items as $item) {
            if (! ($this->run($runId)?->status->isActive() ?? false)) {
                return true;
            }

            try {
                $outcome = $this->importer->import($run, $item);
            } catch (Throwable $exception) {
                report($exception);
                $outcome = new ImportItemOutcome(ImportLogResult::Error, $exception instanceof DomainException ? $exception->getMessage() : __('catalog::imports.errors.item_failed', ['detail' => mb_substr($exception->getMessage(), 0, 200)]), imagesTotal: count($item->images));
            }

            $this->record($run, $outcome, ['external_id' => $item->externalId, 'sku' => $item->sku, 'item_name' => $item->displayName(), 'product_id' => $outcome->productId]);
        }

        $this->imports->increment($run, ['pages_done' => 1]);

        return true;
    }

    /**
     * A page that could not be read even after the job's retries.
     */
    public function pageFailed(int $runId, int $page, string $message): void
    {
        $run = $this->run($runId);

        if ($run === null) {
            return;
        }

        $this->imports->log($run, ImportLogResult::Error, __('catalog::imports.messages.page_failed', ['page' => $page, 'detail' => $message]));
        $this->imports->increment($run, ['error_count' => 1, 'pages_done' => 1]);
    }

    public function finish(int $runId, bool $everyPageFailed = false): void
    {
        $run = $this->run($runId);

        if ($run === null || ! in_array($run->status, [ImportStatus::Running, ImportStatus::Paused], true)) {
            return;
        }

        $failed = $everyPageFailed || ($run->processed_count === 0 && $run->error_count > 0);
        $this->imports->update($run, [
            'status' => $failed ? ImportStatus::Failed : ImportStatus::Completed,
            'failure_message' => $failed ? __('catalog::imports.errors.all_pages_failed') : null,
            'finished_at' => now(),
        ]);
        $this->dispatchAfterCommit(new CatalogImportFinished($run->id));
    }

    public function pause(CatalogImportRun $run): CatalogImportRun
    {
        if (! $run->status->canPause()) {
            throw new DomainException(__('catalog::imports.errors.cannot_pause'));
        }

        return $this->imports->update($run, ['status' => ImportStatus::Paused]);
    }

    public function resume(CatalogImportRun $run): CatalogImportRun
    {
        if (! $run->status->canResume()) {
            throw new DomainException(__('catalog::imports.errors.cannot_resume'));
        }

        return $this->imports->update($run, ['status' => ImportStatus::Running]);
    }

    public function cancel(CatalogImportRun $run): CatalogImportRun
    {
        if (! $run->status->canCancel()) {
            throw new DomainException(__('catalog::imports.errors.cannot_cancel'));
        }

        $this->imports->update($run, ['status' => ImportStatus::Cancelled, 'finished_at' => now()]);

        if ($run->batch_id !== null) {
            Bus::findBatch($run->batch_id)?->cancel();
        }

        $this->dispatchAfterCommit(new CatalogImportFinished($run->id));

        return $run;
    }

    /**
     * Live payload polled by the import page.
     *
     * @return array<string, mixed>
     */
    public function progress(CatalogImportRun $run): array
    {
        $run->refresh();

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'status_tone' => $run->status->tone(),
            'active' => $run->status->isActive(),
            'can_pause' => $run->status->canPause(),
            'can_resume' => $run->status->canResume(),
            'can_cancel' => $run->status->canCancel(),
            'products' => ['done' => $run->processed_count, 'total' => max($run->total_items, $run->processed_count), 'percent' => $run->percent($run->processed_count, max($run->total_items, $run->processed_count))],
            'images' => ['done' => $run->images_done, 'total' => $run->images_total, 'percent' => $run->percent($run->images_done, $run->images_total)],
            'pages' => ['done' => $run->pages_done, 'total' => (int) $run->total_pages],
            'counts' => [
                'created' => $run->created_count,
                'updated' => $run->updated_count,
                'skipped' => $run->skipped_count,
                'errors' => $run->error_count,
            ],
            'failure' => $run->failure_message,
            'finished_at' => $run->finished_at?->toIso8601String(),
        ];
    }

    public function run(int $id): ?CatalogImportRun
    {
        /** @var CatalogImportRun|null $run */
        $run = $this->imports->find($id);

        return $run;
    }

    /**
     * @param  array{external_id?: int|null, sku?: string|null, item_name?: string|null, product_id?: int|null}  $item
     */
    private function record(CatalogImportRun $run, ImportItemOutcome $outcome, array $item): void
    {
        $this->imports->log($run, $outcome->result, $outcome->message, $item);

        foreach ($outcome->warnings as $warning) {
            $this->imports->log($run, ImportLogResult::Warning, $warning, $item);
        }

        $counter = $outcome->result->counter();
        $this->imports->increment($run, array_filter([
            'processed_count' => 1,
            'images_total' => $outcome->imagesTotal,
            'images_done' => $outcome->imagesDone,
            ...($counter !== null ? [$counter => 1] : []),
        ]));
    }

    private function fail(CatalogImportRun $run, string $message): void
    {
        $this->imports->log($run, ImportLogResult::Error, $message);
        $this->imports->update($run, [
            'status' => ImportStatus::Failed,
            'failure_message' => mb_substr($message, 0, 500),
            'error_count' => $run->error_count + 1,
            'finished_at' => now(),
        ]);
        $this->dispatchAfterCommit(new CatalogImportFinished($run->id));
    }

    /**
     * @throws DomainException when the source cannot be read
     */
    private function fetch(ImportDriver $driver, int $page): ExternalCatalogPage
    {
        try {
            return $this->drivers->driver($driver)->fetchPage($page);
        } catch (ExternalCatalogException $exception) {
            throw new DomainException($exception->getMessage(), 0, $exception);
        }
    }

    private function connection(): ?string
    {
        return (new CatalogImportRun)->getConnectionName();
    }
}
