<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Contracts\CertificateInspector;
use App\Modules\Platform\Contracts\DnsResolver;
use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\CreatingDatabase;
use Tests\Support\TestDatabaseGuard;

/*
| Shared helpers for the vendors / registration / compliance / domains feature tests
| (required by each test file).
*/

if (! function_exists('vendorsAdmin')) {
    /**
     * Admin with the given permissions (or super-admin when null).
     *
     * @param  list<string>|null  $permissions
     */
    function vendorsAdmin(?array $permissions = null): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create();

        $permissions === null
            ? $admin->assignRole('super-admin')
            : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }

    /**
     * A central tenant row without a store database (lists, profile, compliance, domains).
     *
     * @param  array<string, mixed>  $attributes
     */
    function vendorsTenant(array $attributes = []): Tenant
    {
        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'name' => 'Megz Store',
            'slug' => 'store-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
            'owner_name' => 'Mostafa Gamal',
            'owner_email' => 'owner-'.Str::lower(Str::random(6)).'@example.com',
            'owner_phone' => '+966550102030',
        ], $attributes)));
    }

    /**
     * Drop store databases created by TenantProvisioningService after the test (and
     * any stale one with the same test-marked name before creation).
     */
    function cleanUpProvisionedTenants(): void
    {
        $created = new ArrayObject;

        Event::listen(CreatingDatabase::class, static function (CreatingDatabase $event) use ($created): void {
            $database = $event->tenant->database();
            TestDatabaseGuard::assertSafeName($database->getName(), 'tenant test database');

            if ($database->manager()->databaseExists($database->getName())) {
                $database->manager()->deleteDatabase($event->tenant);
            }

            $created->append($event->tenant);
        });

        test()->beforeApplicationDestroyed(static function () use ($created): void {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            foreach ($created as $tenant) {
                try {
                    $database = $tenant->database();

                    if (TestDatabaseGuard::isSafeName($database->getName()) && $database->manager()->databaseExists($database->getName())) {
                        $database->manager()->deleteDatabase($tenant);
                    }
                } catch (Throwable) {
                    // Best effort: a failed cleanup must not mask the test result.
                }
            }
        });
    }

    /**
     * Fake DNS: host|TYPE => values.
     *
     * @param  array<string, list<string>>  $records
     */
    function fakeDns(array $records, ?Carbon $sslUntil = null): void
    {
        app()->instance(DnsResolver::class, new class($records) implements DnsResolver
        {
            /**
             * @param  array<string, list<string>>  $records
             */
            public function __construct(private readonly array $records) {}

            public function lookup(string $host, DnsRecordType $type): array
            {
                return $this->records[$host.'|'.$type->value] ?? [];
            }
        });

        app()->instance(CertificateInspector::class, new class($sslUntil) implements CertificateInspector
        {
            public function __construct(private readonly ?Carbon $until) {}

            public function validUntil(string $host): ?Carbon
            {
                return $this->until;
            }
        });
    }
}
