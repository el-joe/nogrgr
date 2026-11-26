<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Active staff members holding a permission (vendor guard).
     *
     * @return Collection<int, VendorStaff>
     */
    public function activeWithPermission(string $permission): Collection;

    /**
     * Staff list query for the team table (VEN-076) with roles eager-loaded.
     *
     * @return Builder<VendorStaff>
     */
    public function listQuery(): Builder;

    /**
     * @return array{all: int, active: int, pending: int, suspended: int}
     */
    public function counts(): array;

    public function emailTaken(string $email, ?int $ignoreId = null): bool;
}
