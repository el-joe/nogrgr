<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Events\VendorStaffJoined;
use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Auth\Events\PasswordReset;

/**
 * An invited store team member (status pending, VEN-076a) becomes active the moment
 * they set their password from the invitation link; the link proves the e-mail too.
 */
class ActivateInvitedVendorStaff
{
    public function handle(PasswordReset $event): void
    {
        $staff = $event->user;

        if (! $staff instanceof VendorStaff || $staff->status !== AccountStatus::Pending) {
            return;
        }

        $staff->forceFill([
            'status' => AccountStatus::Active,
            'email_verified_at' => $staff->email_verified_at ?? now(),
        ])->save();

        if (tenancy()->initialized) {
            VendorStaffJoined::dispatch((int) tenant()?->getTenantKey(), $staff->id);
        }
    }
}
