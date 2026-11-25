<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\AdminData;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Events\AdminAccountCreated;
use App\Modules\Identity\Events\AdminPasswordChanged;
use App\Modules\Identity\Events\AdminRoleChanged;
use App\Modules\Identity\Exceptions\AdminRuleException;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;

/**
 * Admins (ADM-110 / 110a). Rules: nobody deletes or deactivates themselves; the last
 * active super-admin can never be deleted, deactivated or demoted; only a super-admin
 * may grant the super-admin role or change a super-admin. Account create/update/delete
 * are audited by the Auditable trait; role changes are audited here.
 */
class AdminService extends BaseService
{
    public function __construct(
        private readonly AdminRepositoryContract $admins,
        private readonly Auditor $auditor,
    ) {}

    public function create(AdminData $data, Admin $actor): Admin
    {
        $this->guardSuperAdminRole($data->role, $actor);

        $admin = $this->transaction(function () use ($data): Admin {
            /** @var Admin $admin */
            $admin = $this->admins->create([
                'name' => $data->name,
                'email' => strtolower($data->email),
                'password' => (string) $data->password,
                'status' => $data->status,
                'locale' => app()->getLocale(),
            ]);
            $admin->assignRole($data->role);
            $this->auditor->record('admin.role_changed', $admin, ['before' => ['role' => null], 'after' => ['role' => $data->role]]);

            return $admin;
        }, $this->connection());

        AdminAccountCreated::dispatch($admin, $actor);

        return $admin;
    }

    public function update(Admin $admin, AdminData $data, Admin $actor): Admin
    {
        $currentRole = $this->roleOf($admin);

        if ($admin->isSuperAdmin() || $data->role === SystemRole::SuperAdmin->value) {
            $this->guardSuperAdminActor($actor);
        }

        $this->guardStatus($admin, $data->status, $actor);
        $this->guardLastSuperAdmin($admin, $data->role, $data->status);

        $this->transaction(function () use ($admin, $data, $currentRole): void {
            $attributes = ['name' => $data->name, 'email' => strtolower($data->email), 'status' => $data->status];

            if ($data->password !== null && $data->password !== '') {
                $attributes['password'] = $data->password;
            }

            $this->admins->update($admin, $attributes);

            if ($currentRole !== $data->role) {
                $admin->syncRoles([$data->role]);
                $this->auditor->record('admin.role_changed', $admin, ['before' => ['role' => $currentRole], 'after' => ['role' => $data->role]]);
            }
        }, $this->connection());

        if ($data->password !== null && $data->password !== '') {
            $this->auditor->record('admin.password_reset_by_admin', $admin);
            AdminPasswordChanged::dispatch($admin, $admin->is($actor));
        }

        if ($currentRole !== $data->role) {
            AdminRoleChanged::dispatch($admin, $currentRole, $data->role);
        }

        return $admin->refresh();
    }

    public function setStatus(Admin $admin, AccountStatus $status, Admin $actor): Admin
    {
        if ($admin->isSuperAdmin()) {
            $this->guardSuperAdminActor($actor);
        }

        $this->guardStatus($admin, $status, $actor);
        $this->guardLastSuperAdmin($admin, (string) $this->roleOf($admin), $status);

        return $this->admins->update($admin, ['status' => $status]);
    }

    public function delete(Admin $admin, Admin $actor): void
    {
        if ($admin->is($actor)) {
            throw AdminRuleException::cannotDeleteSelf();
        }

        if ($admin->isSuperAdmin()) {
            $this->guardSuperAdminActor($actor);

            if ($admin->status === AccountStatus::Active && $this->admins->countActiveWithRole(SystemRole::SuperAdmin->value) <= 1) {
                throw AdminRuleException::lastSuperAdmin();
            }
        }

        $this->transaction(function () use ($admin): void {
            $admin->syncRoles([]);
            $this->admins->delete($admin);
        }, $this->connection());
    }

    /**
     * @return array{all: int, active: int, suspended: int, pending: int}
     */
    public function counts(): array
    {
        return $this->admins->counts();
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return $this->admins->options();
    }

    public function roleOf(Admin $admin): ?string
    {
        $role = $admin->getRoleNames()->first();

        return is_string($role) ? $role : null;
    }

    private function guardStatus(Admin $admin, AccountStatus $status, Admin $actor): void
    {
        if ($admin->is($actor) && $status !== AccountStatus::Active) {
            throw AdminRuleException::cannotDeactivateSelf();
        }
    }

    private function guardLastSuperAdmin(Admin $admin, string $role, AccountStatus $status): void
    {
        $staysSuperAdmin = $role === SystemRole::SuperAdmin->value && $status === AccountStatus::Active;

        if ($admin->isSuperAdmin() && $admin->status === AccountStatus::Active && ! $staysSuperAdmin
            && $this->admins->countActiveWithRole(SystemRole::SuperAdmin->value) <= 1) {
            throw AdminRuleException::lastSuperAdmin();
        }
    }

    private function guardSuperAdminRole(string $role, Admin $actor): void
    {
        if ($role === SystemRole::SuperAdmin->value) {
            $this->guardSuperAdminActor($actor);
        }
    }

    private function guardSuperAdminActor(Admin $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw AdminRuleException::superAdminOnly();
        }
    }

    private function connection(): ?string
    {
        return (new Admin)->getConnectionName();
    }
}
