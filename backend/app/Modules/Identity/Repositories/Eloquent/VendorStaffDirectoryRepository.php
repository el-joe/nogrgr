<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Models\VendorStaffDirectoryEntry;
use App\Modules\Identity\Repositories\Contracts\VendorStaffDirectoryRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VendorStaffDirectoryEntry>
 */
class VendorStaffDirectoryRepository extends BaseRepository implements VendorStaffDirectoryRepositoryContract
{
    protected function model(): string
    {
        return VendorStaffDirectoryEntry::class;
    }

    public function findByEmailHash(string $emailHash): Collection
    {
        return VendorStaffDirectoryEntry::withoutTenantScope()
            ->where('email_hash', $emailHash)
            ->orderBy('tenant_id')
            ->get();
    }

    public function upsertEntry(int $tenantId, int $vendorStaffId, string $emailHash): VendorStaffDirectoryEntry
    {
        return VendorStaffDirectoryEntry::withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $tenantId, 'vendor_staff_id' => $vendorStaffId],
            ['email_hash' => $emailHash],
        );
    }

    public function deleteEntry(int $tenantId, int $vendorStaffId): void
    {
        VendorStaffDirectoryEntry::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('vendor_staff_id', $vendorStaffId)
            ->delete();
    }
}
