<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\RoleData;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Exceptions\AdminRuleException;
use App\Modules\Identity\Repositories\Contracts\RoleRepositoryContract;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin roles (ADM-111 / 111a) on spatie/permission. Saved permissions always include
 * the implied "view" of every "manage" (PermissionCatalog::withImplied). The
 * super-admin role is locked; a role still assigned to an admin cannot be deleted.
 * Every change is audited with the before/after permission lists.
 */
class RoleService extends BaseService
{
    public function __construct(
        private readonly RoleRepositoryContract $roles,
        private readonly Auditor $auditor,
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function create(RoleData $data, string $guard = 'admin'): Role
    {
        $permissions = $this->normalize($data->permissions, $guard);

        /** @var Role $role */
        $role = $this->transaction(function () use ($data, $guard, $permissions): Role {
            /** @var Role $role */
            $role = $this->roles->create(['name' => $data->name, 'guard_name' => $guard]);
            $role->syncPermissions($permissions);

            return $role;
        }, $this->connection());

        $this->registrar->forgetCachedPermissions();
        $this->auditor->record('role.created', $role, ['after' => ['name' => $role->name, 'permissions' => $permissions]]);

        return $role;
    }

    public function update(Role $role, RoleData $data): Role
    {
        $this->guardLocked($role);

        $before = ['name' => $role->name, 'permissions' => $this->permissionsOf($role)];
        $permissions = $this->normalize($data->permissions, $role->guard_name);

        $this->transaction(function () use ($role, $data, $permissions): void {
            $this->roles->update($role, ['name' => $data->name]);
            $role->syncPermissions($permissions);
        }, $this->connection());

        $this->registrar->forgetCachedPermissions();
        $after = ['name' => $role->name, 'permissions' => $permissions];

        if ($before !== $after) {
            $this->auditor->record('role.updated', $role, ['before' => $before, 'after' => $after]);
        }

        return $role;
    }

    public function delete(Role $role): void
    {
        $this->guardLocked($role);

        $assigned = $this->roles->usersCount($role);

        if ($assigned > 0) {
            throw AdminRuleException::roleInUse($assigned, $role->guard_name);
        }

        $before = ['name' => $role->name, 'permissions' => $this->permissionsOf($role)];
        $this->roles->delete($role);
        $this->registrar->forgetCachedPermissions();
        $this->auditor->record('role.deleted', $role, ['before' => $before]);
    }

    /**
     * @return list<string>
     */
    public function permissionsOf(Role $role): array
    {
        $names = $role->permissions->pluck('name')->map(fn (mixed $name): string => (string) $name)->sort()->values()->all();

        return array_values($names);
    }

    public function isLocked(Role $role): bool
    {
        return SystemRole::lockedFor($role->name, $role->guard_name);
    }

    public function usersCount(Role $role): int
    {
        return $this->roles->usersCount($role);
    }

    public function find(int $id, string $guard = 'admin'): ?Role
    {
        return $this->roles->findForGuard($id, $guard);
    }

    /**
     * @return array<string, string>
     */
    public function options(string $guard = 'admin'): array
    {
        return $this->roles->options($guard);
    }

    public function count(string $guard = 'admin'): int
    {
        return $this->roles->countForGuard($guard);
    }

    /**
     * Catalog-only names + implied views, sorted.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function normalize(array $permissions, string $guard): array
    {
        $known = PermissionCatalog::forGuard($guard);
        $names = PermissionCatalog::withImplied(array_values(array_intersect($permissions, $known)), $guard);
        sort($names);
        $this->roles->ensurePermissions($names, $guard);

        return $names;
    }

    private function guardLocked(Role $role): void
    {
        if ($this->isLocked($role)) {
            throw AdminRuleException::roleLocked($role->guard_name);
        }
    }

    private function connection(): ?string
    {
        return $this->roles->query()->getModel()->getConnectionName();
    }
}
