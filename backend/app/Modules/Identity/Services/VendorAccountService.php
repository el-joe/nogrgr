<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\VendorAccountData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;

/**
 * Vendor › Settings › Account (VEN-070, Figma Vendor Panel 90:111315): the signed-in staff
 * member's own profile (name, e-mail, phone, panel language) and password.
 *  - The store owner's e-mail and sign-in methods belong to the central owner account
 *    (owner portal OWN-006): the e-mail is read-only here, a password change goes through
 *    OwnerProfileService so every store of the owner gets it (SyncOwnerStaffPassword), and
 *    "Linked sign-in" shows the owner's Google / Apple links.
 *  - Other staff: a new e-mail must be confirmed again (verification link is sent).
 */
class VendorAccountService extends BaseService
{
    public function __construct(
        private readonly OwnerRepositoryContract $owners,
        private readonly OwnerProfileService $ownerProfiles,
        private readonly Auditor $auditor,
    ) {}

    public function isStoreOwner(VendorStaff $staff): bool
    {
        return (bool) $staff->getAttribute('is_owner');
    }

    public function update(VendorStaff $staff, VendorAccountData $data): VendorStaff
    {
        $before = ['name' => $staff->name, 'email' => $staff->email, 'phone' => $staff->phone, 'locale' => $staff->locale];
        $attributes = ['name' => $data->name, 'phone' => $data->phone, 'locale' => $data->locale];
        $emailChanged = false;

        if (! $this->isStoreOwner($staff) && $data->email !== null && $data->email !== $staff->email) {
            $attributes['email'] = $data->email;
            $attributes['email_verified_at'] = null;
            $emailChanged = true;
        }

        $staff->forceFill($attributes)->save();

        if ($emailChanged) {
            $staff->sendEmailVerificationNotification();
        }

        $after = ['name' => $staff->name, 'email' => $staff->email, 'phone' => $staff->phone, 'locale' => $staff->locale];

        if ($before !== $after) {
            $this->auditor->record('account.updated', $staff, ['before' => $before, 'after' => $after]);
        }

        return $staff;
    }

    public function changePassword(VendorStaff $staff, string $password): void
    {
        $staff->forceFill(['password' => $password])->save();
        $owner = $this->isStoreOwner($staff) ? $this->owner() : null;

        if ($owner !== null && strcasecmp($owner->email, (string) $staff->email) === 0) {
            $this->ownerProfiles->changePassword($owner, $password);
        }

        $this->auditor->record('account.password_changed', $staff);
    }

    /**
     * "Linked sign-in" rows of the store owner (null for other staff).
     *
     * @return list<array{provider: SocialProvider, linked: bool, detail: string|null}>|null
     */
    public function linkedSignIn(VendorStaff $staff): ?array
    {
        $owner = $this->isStoreOwner($staff) ? $this->owner() : null;

        if ($owner === null) {
            return null;
        }

        $rows = [];

        foreach (SocialProvider::cases() as $provider) {
            $linked = $this->ownerProfiles->linked($owner, $provider);
            $rows[] = ['provider' => $provider, 'linked' => $linked, 'detail' => $linked ? $owner->email : null];
        }

        return $rows;
    }

    private function owner(): ?Owner
    {
        $tenant = tenancy()->initialized ? tenant() : null;
        $ownerId = $tenant instanceof Tenant ? $tenant->owner_id : null;
        $owner = $ownerId !== null ? $this->owners->find($ownerId) : null;

        return $owner instanceof Owner ? $owner : null;
    }
}
