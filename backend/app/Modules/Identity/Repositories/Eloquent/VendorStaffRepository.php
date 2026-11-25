<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VendorStaff>
 */
class VendorStaffRepository extends BaseRepository implements VendorStaffRepositoryContract
{
    protected function model(): string
    {
        return VendorStaff::class;
    }

    public function firstActiveOwner(): ?VendorStaff
    {
        return $this->query()
            ->where('is_owner', true)
            ->where('status', AccountStatus::Active)
            ->orderBy('id')
            ->first();
    }

    public function activeOwners(): Collection
    {
        return $this->query()
            ->where('is_owner', true)
            ->where('status', AccountStatus::Active)
            ->orderBy('id')
            ->get();
    }
}
