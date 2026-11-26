<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Services\PermissionSyncService;
use Illuminate\Database\Seeder;

class CentralPermissionSeeder extends Seeder
{
    public function run(PermissionSyncService $permissions): void
    {
        $permissions->central();
    }
}
