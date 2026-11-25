<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Runs inside the tenant database (tenants:seed / provisioning pipeline).
 */
class TenantPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::tenant() as $guard => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, $guard);
            }
        }

        Role::findOrCreate('owner', 'vendor')->syncPermissions(PermissionCatalog::tenant()['vendor']);
    }
}
