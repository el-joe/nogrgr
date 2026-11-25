<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

/*
| Shared helpers of the Shipping feature tests (required by each test file).
*/

if (! function_exists('shippingAdmin')) {
    /**
     * Admin with the given permissions (or super-admin when null).
     *
     * @param  list<string>|null  $permissions
     */
    function shippingAdmin(?array $permissions = null): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create();

        $permissions === null
            ? $admin->assignRole('super-admin')
            : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }
}
