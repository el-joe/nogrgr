<?php

namespace Database\Seeders;

use App\Modules\Identity\Database\Seeders\TenantPermissionSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeds a freshly provisioned tenant database (`php artisan tenants:seed`).
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantPermissionSeeder::class,
        ]);
    }
}
