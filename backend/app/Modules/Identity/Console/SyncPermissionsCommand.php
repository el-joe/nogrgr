<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Services\PermissionSyncService;
use Illuminate\Console\Command;

/**
 * Syncs PermissionCatalog into the central database and every store (QA-P5-019): new
 * permissions are created, super-admin / affiliate (central) and owner (store) roles get
 * exactly the catalog permissions of their guard, custom roles stay as they are. Part of
 * every deploy (docs/plan/deploy.md); idempotent.
 *
 *   php artisan permissions:sync
 *   php artisan permissions:sync --tenant=12 --skip-central
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync
        {--tenant=* : Only these store ids}
        {--skip-central : Do not sync the central database}';

    protected $description = 'Sync the permission catalog into the central database and every store';

    public function handle(PermissionSyncService $permissions): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('tenant'))));
        $central = ! $this->option('skip-central');

        $report = $permissions->run($ids, $central);

        $this->table(['', 'Permissions created', 'Granted to system roles'], array_values(array_filter([
            $central ? ['Central', $report->centralCreated, $report->centralGranted] : null,
            ["Stores ({$report->stores})", $report->storeCreated, $report->storeGranted],
        ])));

        foreach ($report->failures as $tenantId => $message) {
            $this->components->error("Store {$tenantId}: {$message}");
        }

        $this->components->info("Permissions synced for {$report->stores} store(s).");

        return $report->failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
