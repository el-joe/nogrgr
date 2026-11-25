<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

/*
| Shared helpers for tests/Feature/Integrations (required by each test file).
*/

if (! function_exists('integrationsAdmin')) {
    function integrationsAdmin(string ...$permissions): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }
}
