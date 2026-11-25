<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\VendorStaffDirectoryEntry;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<VendorStaffDirectoryEntry>
 */
interface VendorStaffDirectoryRepositoryContract extends RepositoryContract
{
    /**
     * All stores (across tenants) for an email hash.
     *
     * @return Collection<int, VendorStaffDirectoryEntry>
     */
    public function findByEmailHash(string $emailHash): Collection;

    public function upsertEntry(int $tenantId, int $vendorStaffId, string $emailHash): VendorStaffDirectoryEntry;

    public function deleteEntry(int $tenantId, int $vendorStaffId): void;
}
