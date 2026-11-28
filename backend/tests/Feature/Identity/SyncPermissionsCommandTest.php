<?php

use App\Modules\Identity\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| QA-P5-019: `permissions:sync` brings PermissionCatalog into the central database and
| every store; super-admin / affiliate / owner get exactly their catalog permissions,
| custom roles are left alone. Idempotent.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('permsync');
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->artisan('permissions:sync', ['--tenant' => [$this->tenant->id]])->assertSuccessful();
});

it('gives store owners and super admins permissions added after provisioning, leaving custom roles alone', function (): void {
    // A store provisioned before `catalog.badges.*` existed, with a custom role.
    $this->tenant->run(function (): void {
        Permission::query()->where('guard_name', 'vendor')->where('name', 'like', 'catalog.badges.%')->delete();
        Role::findOrCreate('packer', 'vendor')->syncPermissions(['orders.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });
    Role::findByName('super-admin', 'admin')->revokePermissionTo('finance.ledger.view');

    $this->actingAsVendor($this->owner, $this->tenant)
        ->get($this->tenantUrl($this->tenant, '/admin/catalog/badges'))->assertForbidden();

    $this->artisan('permissions:sync', ['--tenant' => [$this->tenant->id]])
        ->expectsOutputToContain('Permissions synced for 1 store(s).')
        ->assertSuccessful();

    $vendor = PermissionCatalog::assignable('vendor'); // internal permissions excluded (#116)
    expect($vendor)->not->toContain('ui-kit.view');
    $this->tenant->run(function () use ($vendor): void {
        expect(Role::findByName('owner', 'vendor')->permissions->pluck('name')->sort()->values()->all())->toBe(collect($vendor)->sort()->values()->all())
            ->and(Role::findByName('packer', 'vendor')->permissions->pluck('name')->all())->toBe(['orders.view']);
    });
    expect(Role::findByName('super-admin', 'admin')->hasPermissionTo('finance.ledger.view'))->toBeTrue();

    $this->actingAsVendor($this->owner, $this->tenant)
        ->get($this->tenantUrl($this->tenant, '/admin/catalog/badges'))->assertOk();
});

it('is idempotent and can skip the central database', function (): void {
    $this->artisan('permissions:sync', ['--tenant' => [$this->tenant->id]])
        ->expectsTable(['', 'Permissions created', 'Granted to system roles'], [['Central', 0, 0], ['Stores (1)', 0, 0]])
        ->assertSuccessful();

    Role::findByName('super-admin', 'admin')->revokePermissionTo('finance.ledger.view');

    $this->artisan('permissions:sync', ['--tenant' => [$this->tenant->id], '--skip-central' => true])
        ->expectsTable(['', 'Permissions created', 'Granted to system roles'], [['Stores (1)', 0, 0]])
        ->assertSuccessful();

    expect(Role::findByName('super-admin', 'admin')->hasPermissionTo('finance.ledger.view'))->toBeFalse();
});
