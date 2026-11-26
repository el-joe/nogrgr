<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Services\PermissionSyncService;
use Illuminate\Database\Seeder;

/**
 * Runs inside the tenant database (tenants:seed / provisioning pipeline).
 */
class TenantPermissionSeeder extends Seeder
{
    public function run(PermissionSyncService $permissions): void
    {
        $permissions->tenant();
    }
}
