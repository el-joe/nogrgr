<?php

namespace Tests\Concerns;

use App\Modules\Identity\Http\Middleware\EnsureCustomerSessionTenant;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Domain;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Routing\VendorPanel;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Support\Facades\Event;
use LogicException;
use Stancl\Tenancy\Events\CreatingDatabase;
use Tests\Support\TestDatabaseGuard;
use Throwable;

/**
 * Tenancy helpers for feature tests.
 *
 *   $tenant = $this->createTenant('acme');        // provisions nogrgr_tenant_{id}[_test_{token}]
 *   $this->initializeTenancy($tenant);            // switch to the tenant DB
 *   $this->get($this->tenantUrl($tenant, '/'));   // http://acme.localhost/
 *
 * Tenant DB names are always test-marked (nogrgr_tenant_{id}_testing or
 * _test_{token}); the helper aborts the run otherwise (TestDatabaseGuard).
 * Every tenant database created here is dropped after the test, and a stale
 * database with the same name (from an aborted run) is dropped before creation,
 * so test processes never share or leak tenant databases.
 */
trait InteractsWithTenancy
{
    /**
     * @var list<Tenant>
     */
    protected array $tenantsToCleanUp = [];

    protected bool $tenancyCleanupRegistered = false;

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createTenant(string $subdomain = 'acme', array $attributes = []): Tenant
    {
        $this->registerTenancyCleanup();

        Event::listen(CreatingDatabase::class, static function (CreatingDatabase $event): void {
            $tenant = $event->tenant;
            TestDatabaseGuard::assertSafeName($tenant->database()->getName(), 'tenant test database');
            $manager = $tenant->database()->manager();

            if ($manager->databaseExists($tenant->database()->getName())) {
                $manager->deleteDatabase($tenant);
            }
        });

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->create(array_merge([
            'name' => ucfirst($subdomain),
            'slug' => $subdomain,
            'status' => TenantStatus::Active,
        ], $attributes));

        $this->tenantsToCleanUp[] = $tenant;

        $tenant->domains()->create([
            'domain' => $subdomain,
            'type' => DomainType::Subdomain,
            'is_primary' => true,
        ]);

        return $tenant;
    }

    protected function initializeTenancy(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
    }

    protected function endTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    /**
     * Absolute URL on the tenant's sub-domain of the primary central domain.
     */
    protected function tenantUrl(Tenant $tenant, string $path = '/'): string
    {
        /** @var Domain|null $domain */
        $domain = $tenant->domains()->first();
        $host = ($domain->domain ?? 'acme').'.'.CentralDomains::primary();

        return 'http://'.$host.'/'.ltrim($path, '/');
    }

    /**
     * Central-host vendor panel URL: http://localhost/tenant/{slug}/{path}.
     */
    protected function centralPanelUrl(Tenant $tenant, string $path = ''): string
    {
        return rtrim($this->centralUrl('tenant/'.$tenant->slug.'/'.ltrim($path, '/')), '/');
    }

    /**
     * Authenticate a vendor staff member for $tenant exactly like a real login does
     * (guard user + the session binding checked by EnsureVendorSessionTenant).
     */
    protected function actingAsVendor(VendorStaff $staff, Tenant $tenant): static
    {
        return $this->actingAs($staff, 'vendor')->withSession([VendorPanel::SESSION_TENANT => $tenant->id]);
    }

    /**
     * Create an active store owner (role "owner") inside $tenant and return to central context.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createVendorOwner(Tenant $tenant, array $attributes = []): VendorStaff
    {
        $wasInitialized = tenancy()->initialized;
        $this->initializeTenancy($tenant);

        $owner = VendorStaff::factory()->create(array_merge(['is_owner' => true], $attributes));
        $owner->assignRole('owner');

        if (! $wasInitialized) {
            $this->endTenancy();
        }

        return $owner;
    }

    /**
     * Authenticate a storefront customer of $tenant like a real login (guard user + the
     * store binding checked by EnsureCustomerSessionTenant).
     */
    protected function actingAsCustomer(Customer $customer, Tenant $tenant): static
    {
        return $this->actingAs($customer, 'customer')->withSession([EnsureCustomerSessionTenant::SESSION_KEY => $tenant->id]);
    }

    /**
     * Create a customer inside $tenant and return to central context.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createCustomer(Tenant $tenant, array $attributes = []): Customer
    {
        $wasInitialized = tenancy()->initialized;
        $this->initializeTenancy($tenant);

        $customer = Customer::factory()->create($attributes)->fresh() ?? throw new LogicException('Customer not created.');

        if (! $wasInitialized) {
            $this->endTenancy();
        }

        return $customer;
    }

    protected function centralUrl(string $path = '/'): string
    {
        return 'http://'.CentralDomains::primary().'/'.ltrim($path, '/');
    }

    private function registerTenancyCleanup(): void
    {
        if ($this->tenancyCleanupRegistered) {
            return;
        }

        $this->tenancyCleanupRegistered = true;

        $this->beforeApplicationDestroyed(function (): void {
            $this->endTenancy();

            foreach ($this->tenantsToCleanUp as $tenant) {
                try {
                    if (! TestDatabaseGuard::isSafeName($tenant->database()->getName())) {
                        continue;
                    }

                    $manager = $tenant->database()->manager();

                    if ($manager->databaseExists($tenant->database()->getName())) {
                        $manager->deleteDatabase($tenant);
                    }
                } catch (Throwable) {
                    // Best effort: a failed cleanup must not mask the test result.
                }
            }

            $this->tenantsToCleanUp = [];
        });
    }
}
