<?php

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Events\CatalogImportFinished;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Notifications\CatalogImportFinishedNotification;
use App\Modules\Catalog\Repositories\Contracts\CatalogImportRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;

/**
 * Tells the admin who started an import how it ended (inbox + mail + live push).
 */
class NotifyCatalogImportFinished
{
    public function __construct(
        private readonly CatalogImportRepositoryContract $imports,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(CatalogImportFinished $event): void
    {
        /** @var CatalogImportRun|null $run */
        $run = $this->imports->query()->with('starter')->find($event->runId);

        if ($run?->starter === null) {
            return;
        }

        $this->dispatcher->send($run->starter, new CatalogImportFinishedNotification(
            $run->id,
            $run->status->value,
            $run->driver->label(),
            $run->created_count,
            $run->updated_count,
            $run->skipped_count,
            $run->error_count,
        ));
    }
}
