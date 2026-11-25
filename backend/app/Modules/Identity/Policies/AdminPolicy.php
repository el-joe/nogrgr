<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\Admin;

/**
 * Who may change an admin account from Admins & roles. `admins.manage` is required
 * (route middleware too); a super-admin account can only be changed by a super-admin.
 * Business rules (self, last super-admin) are enforced by AdminService.
 */
class AdminPolicy
{
    public function update(Admin $actor, Admin $admin): bool
    {
        return $actor->can('admins.manage') && (! $admin->isSuperAdmin() || $actor->isSuperAdmin());
    }

    public function toggle(Admin $actor, Admin $admin): bool
    {
        return $this->update($actor, $admin);
    }

    public function delete(Admin $actor, Admin $admin): bool
    {
        return $this->update($actor, $admin);
    }
}
