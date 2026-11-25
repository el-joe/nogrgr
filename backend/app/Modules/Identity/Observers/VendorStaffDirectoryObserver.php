<?php

namespace App\Modules\Identity\Observers;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Services\VendorStaffDirectory;

/**
 * Keeps the central vendor_staff_directory in sync with each tenant's vendor_staff.
 */
class VendorStaffDirectoryObserver
{
    public function __construct(private readonly VendorStaffDirectory $directory) {}

    public function created(VendorStaff $staff): void
    {
        $this->directory->sync($staff);
    }

    public function updated(VendorStaff $staff): void
    {
        if ($staff->wasChanged('email')) {
            $this->directory->sync($staff);
        }
    }

    public function deleted(VendorStaff $staff): void
    {
        $this->directory->remove($staff);
    }
}
