<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;

/**
 * Vendor staff e-mail confirmation (VEN-004) on the store host. A link is valid when
 * its signature has not expired and its hash still matches the account's e-mail.
 */
class VendorEmailVerificationService extends BaseService
{
    public function __construct(
        private readonly VendorStaffRepositoryContract $staff,
        private readonly Auditor $auditor,
    ) {}

    /**
     * Returns the verified staff member, or null for an expired / tampered link.
     */
    public function verify(Request $request, string $id, string $hash): ?VendorStaff
    {
        if (! $request->hasValidSignature() || ! ctype_digit($id)) {
            return null;
        }

        $staff = $this->staff->find((int) $id);

        if (! $staff instanceof VendorStaff || ! hash_equals(sha1($staff->getEmailForVerification()), $hash)) {
            return null;
        }

        if (! $staff->hasVerifiedEmail()) {
            $staff->markEmailAsVerified();
            $this->auditor->record(AuditAction::EmailVerified, $staff);
        }

        return $staff;
    }

    /**
     * Sends a fresh link. False when the address is already confirmed.
     */
    public function resend(VendorStaff $staff): bool
    {
        if ($staff->hasVerifiedEmail()) {
            return false;
        }

        $staff->sendEmailVerificationNotification();

        return true;
    }
}
