<?php

namespace App\Modules\Inventory\Database\Seeders;

use App\Modules\Inventory\Services\BranchService;
use Illuminate\Database\Seeder;

/**
 * Fresh installs get the default "Main warehouse" branch (after countries and
 * languages), so stock always has a branch. Idempotent: existing branches are kept.
 */
class DefaultBranchSeeder extends Seeder
{
    public function run(BranchService $branches): void
    {
        $branches->defaultOrCreate();
    }
}
