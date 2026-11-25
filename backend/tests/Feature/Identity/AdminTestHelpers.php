<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

/*
| Shared helpers of the Admins & roles / profile / audit feature tests.
*/

if (! function_exists('superAdmin')) {
    function superAdmin(array $attributes = []): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create($attributes);
        $admin->assignRole('super-admin');

        return $admin;
    }

    /**
     * @param  list<string>  $permissions
     */
    function adminWith(array $permissions, array $attributes = []): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create($attributes);
        $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }
}
