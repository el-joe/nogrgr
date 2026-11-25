<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant-DB repository: call inside tenant context.
 *
 * @extends RepositoryContract<VendorStaff>
 */
interface VendorStaffRepositoryContract extends RepositoryContract
{
    public function firstActiveOwner(): ?VendorStaff;

    /**
     * @return Collection<int, VendorStaff>
     */
    public function activeOwners(): Collection;
}
