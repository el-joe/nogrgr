<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;

/**
 * Creates (if missing), migrates and seeds a store database. Every step is idempotent,
 * so a failed provisioning run can simply be retried. The name comes from
 * TenantDatabaseName (test-marked + ParallelTesting token under tests).
 */
class TenantDatabaseProvisioner
{
    public function ensure(Tenant $tenant): void
    {
        $database = $tenant->database();
        $database->makeCredentials();
        $manager = $database->manager();

        if (! $manager->databaseExists($database->getName())) {
            event(new CreatingDatabase($tenant));
            $manager->createDatabase($tenant);
            event(new DatabaseCreated($tenant));
        }

        $this->artisan('tenants:migrate', $tenant);
        $this->artisan('tenants:seed', $tenant);
    }

    private function artisan(string $command, Tenant $tenant): void
    {
        $status = Artisan::call($command, ['--tenants' => [$tenant->getTenantKey()], '--force' => true]);

        if ($status !== 0) {
            throw new RuntimeException(sprintf('%s failed for store %s: %s', $command, $tenant->getTenantKey(), trim(Artisan::output())));
        }
    }
}
