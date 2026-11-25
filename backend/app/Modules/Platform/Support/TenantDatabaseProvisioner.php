<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;

/**
 * Creates (if missing), migrates and seeds a store database. Every step is idempotent,
 * so a failed provisioning run can simply be retried:
 * - the database is only created when missing;
 * - migrations resume: finished ones are recorded, and PartialMigrationCleaner drops
 *   whatever a failing migration had already created (MySQL DDL is not transactional);
 * - seeders only use findOrCreate / firstOrCreate / upsert.
 * The name comes from TenantDatabaseName (test-marked + ParallelTesting token under tests).
 */
class TenantDatabaseProvisioner
{
    public function __construct(private readonly PartialMigrationCleaner $cleaner) {}

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

        $this->cleaner->arm();

        try {
            $this->artisan('tenants:migrate', $tenant);
        } finally {
            $this->cleaner->disarm();
        }

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
