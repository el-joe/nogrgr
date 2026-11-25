<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CentralPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::central() as $guard => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, $guard);
            }
        }

        Role::findOrCreate('super-admin', 'admin')->syncPermissions(PermissionCatalog::central()['admin']);
        Role::findOrCreate('affiliate', 'affiliate')->syncPermissions(PermissionCatalog::central()['affiliate']);
    }
}
