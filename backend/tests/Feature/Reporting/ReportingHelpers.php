<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Reporting\Models\OrderFact;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/*
| Shared helpers of the Reporting feature tests (required by each test file).
*/

if (! function_exists('reportAdmin')) {
    /**
     * Admin with the given permissions (or super-admin when null).
     *
     * @param  list<string>|null  $permissions
     */
    function reportAdmin(?array $permissions = null): Admin
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        test()->seed(CentralPermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Admin::factory()->create();

        $permissions === null
            ? $admin->assignRole('super-admin')
            : $admin->givePermissionTo(['dashboard.view', ...$permissions]);

        return $admin;
    }

    /**
     * A tenant row without provisioning its database (facts only need the id / name).
     */
    function reportTenant(string $name = 'Megz Store'): Tenant
    {
        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
            'name' => $name,
            'slug' => 'store-'.Str::lower(Str::random(10)),
            'status' => TenantStatus::Active,
        ]));
    }

    /**
     * One order fact (defaults: delivered catalog order of 100.00, platform 20.00, placed 2 days ago).
     *
     * @param  array<string, mixed>  $attributes
     */
    function reportFact(Tenant $tenant, array $attributes = []): OrderFact
    {
        return OrderFact::withoutTenantScope()->create(array_merge([
            'tenant_id' => $tenant->id,
            'order_uuid' => (string) Str::uuid(),
            'order_number' => 'NG-'.Str::upper(Str::random(8)),
            'kind' => 'catalog',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'items_count' => 2,
            'items_grand_total' => 10000,
            'grand_total' => 10000,
            'platform_profit' => 2000,
            'vendor_profit' => 8000,
            'customer_id' => 1,
            'customer_name' => 'Ahmed Ali',
            'customer_email' => 'ahmed@example.com',
            'placed_at' => now()->subDays(2),
            'paid_at' => now()->subDays(2),
            'fulfilment_status' => 'delivered',
        ], $attributes));
    }
}
