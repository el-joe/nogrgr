<?php

namespace App\Modules\Catalog\Notifications;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\PanelNotification;

/**
 * Admin inbox / mail / live push: a catalog import run ended ("system.catalog_import_<status>").
 */
class CatalogImportFinishedNotification extends PanelNotification
{
    public function __construct(
        public readonly int $runId,
        public readonly string $status,
        public readonly string $driver,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        public readonly int $errors,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function event(): string
    {
        return 'catalog_import_'.$this->status;
    }

    protected function titleKey(): string
    {
        return 'catalog::imports.notifications.'.$this->status.'.title';
    }

    protected function bodyKey(): string
    {
        return 'catalog::imports.notifications.'.$this->status.'.body';
    }

    /**
     * @return array<string, string|int>
     */
    protected function params(): array
    {
        return [
            'driver' => $this->driver,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    protected function url(): ?string
    {
        return route('admin.catalog.imports.index', ['run' => $this->runId]);
    }
}
