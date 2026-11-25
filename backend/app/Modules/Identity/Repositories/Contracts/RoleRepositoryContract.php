<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Panel roles (spatie/permission) of one guard.
 *
 * @extends RepositoryContract<Role>
 */
interface RoleRepositoryContract extends RepositoryContract
{
    /**
     * Roles of the guard with `users_count` and `permissions_count`.
     *
     * @return Builder<Role>
     */
    public function listQuery(string $guard): Builder;

    public function findForGuard(int $id, string $guard): ?Role;

    public function findByName(string $name, string $guard): ?Role;

    public function usersCount(Role $role): int;

    /**
     * name => label for selects.
     *
     * @return array<string, string>
     */
    public function options(string $guard): array;

    public function countForGuard(string $guard): int;

    /**
     * Make sure every permission name exists for the guard (catalog entries added after
     * the last seed).
     *
     * @param  list<string>  $names
     */
    public function ensurePermissions(array $names, string $guard): void;
}
