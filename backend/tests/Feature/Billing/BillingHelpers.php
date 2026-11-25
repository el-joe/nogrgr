<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Str;

/*
| Shared helpers for the Billing feature tests (required by each test file).
*/

if (! function_exists('billingAdmin')) {
    /**
     * Admin with the given permissions (or super-admin when null).
     *
     * @param  list<string>|null  $permissions
     */
    function billingAdmin(?array $permissions = null): Admin
    {
        test()->seed(CentralPermissionSeeder::class);
        $admin = Admin::factory()->create();

        $permissions === null
            ? $admin->assignRole('super-admin')
            : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }

    /**
     * A tenant row without provisioning its database (billing rows only need the FK).
     */
    function billingTenant(string $name = 'Megz Store'): Tenant
    {
        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
            'name' => $name,
            'slug' => 'store-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
        ]));
    }
}
