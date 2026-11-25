<?php

namespace App\Modules\Identity\Repositories\Contracts;

use App\Modules\Identity\Models\Admin;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<Admin>
 */
interface AdminRepositoryContract extends RepositoryContract
{
    /**
     * Active admins holding the role (e.g. "super-admin").
     */
    public function countActiveWithRole(string $role): int;

    /**
     * @return Collection<int, Admin>
     */
    public function activeWithRole(string $role): Collection;

    /**
     * Active admins allowed to `$permission` (direct, via role, "manage" implying
     * "view", or super-admin).
     *
     * @return Collection<int, Admin>
     */
    public function activeWithPermission(string $permission): Collection;

    /**
     * id => "Name (email)" for filters.
     *
     * @return array<int, string>
     */
    public function options(): array;

    /**
     * @return array{all: int, active: int, suspended: int, pending: int}
     */
    public function counts(): array;
}
