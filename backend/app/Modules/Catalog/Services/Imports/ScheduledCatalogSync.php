<?php

namespace App\Modules\Catalog\Services\Imports;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Imports\ExternalCatalogManager;
use App\Modules\Integrations\Services\IntegrationSettingsService;
use Throwable;

/**
 * Daily `--update-existing` sync of an external catalog, switched on in its Integrations
 * section (Neozena: `daily_sync` + `daily_sync_at`, off by default; decision #69). The
 * scheduler only registers the task while the driver is configured and the sync is on.
 */
class ScheduledCatalogSync
{
    public const DEFAULT_TIME = '03:30';

    public function __construct(
        private readonly IntegrationSettingsService $settings,
        private readonly ExternalCatalogManager $drivers,
    ) {}

    /**
     * "HH:MM" the daily sync runs at, or null while it is off / not configured. Never
     * throws: the schedule is also built where the DB is not reachable (fresh install).
     */
    public function dailyAt(ImportDriver $driver): ?string
    {
        try {
            $values = $this->settings->values($driver->integrationSection());

            if (($values['daily_sync'] ?? false) !== true || ! $this->drivers->driver($driver)->isConfigured()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        $time = $values['daily_sync_at'] ?? null;

        return is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : self::DEFAULT_TIME;
    }
}
