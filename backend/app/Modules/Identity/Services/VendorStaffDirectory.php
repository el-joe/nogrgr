<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Models\VendorStaffDirectoryEntry;
use App\Modules\Identity\Repositories\Contracts\VendorStaffDirectoryRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * Central index of vendor staff emails → (tenant, vendor_staff id). Emails are stored
 * as an HMAC (app key) so the central DB never holds tenant staff emails in clear.
 */
class VendorStaffDirectory extends BaseService
{
    public function __construct(private readonly VendorStaffDirectoryRepositoryContract $entries) {}

    public static function hashEmail(string $email): string
    {
        $key = (string) config('app.key');

        return hash_hmac('sha256', mb_strtolower(trim($email)), $key !== '' ? $key : 'nogrgr');
    }

    /**
     * Called (in tenant context) whenever a staff member is created or their email changes.
     */
    public function sync(VendorStaff $staff): void
    {
        if ($staff->email === null || $staff->email === '') {
            $this->remove($staff);

            return;
        }

        $this->entries->upsertEntry($this->currentTenantId(), $staff->id, self::hashEmail($staff->email));
    }

    public function remove(VendorStaff $staff): void
    {
        $this->entries->deleteEntry($this->currentTenantId(), $staff->id);
    }

    /**
     * @return Collection<int, VendorStaffDirectoryEntry>
     */
    public function lookup(string $email): Collection
    {
        return $this->entries->findByEmailHash(self::hashEmail($email));
    }

    private function currentTenantId(): int
    {
        $key = tenancy()->initialized ? tenant()?->getTenantKey() : null;

        if ($key === null) {
            throw new LogicException('Vendor staff directory sync requires tenant context.');
        }

        return (int) $key;
    }
}
