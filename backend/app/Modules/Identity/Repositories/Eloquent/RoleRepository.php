<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Identity\Repositories\Contracts\RoleRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use App\Shared\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @extends BaseRepository<Role>
 */
class RoleRepository extends BaseRepository implements RoleRepositoryContract
{
    protected function model(): string
    {
        return Role::class;
    }

    public function listQuery(string $guard): Builder
    {
        $tables = (array) config('permission.table_names');

        return $this->query()
            ->where('roles.guard_name', $guard)
            ->select('roles.*')
            ->selectSub(
                DB::table((string) $tables['model_has_roles'])->selectRaw('count(*)')
                    ->whereColumn('role_id', 'roles.id')
                    ->where('model_type', $this->userMorphClass($guard)),
                'users_count',
            )
            ->selectSub(
                DB::table((string) $tables['role_has_permissions'])->selectRaw('count(*)')->whereColumn('role_id', 'roles.id'),
                'permissions_count',
            );
    }

    public function findForGuard(int $id, string $guard): ?Role
    {
        return $this->query()->where('guard_name', $guard)->whereKey($id)->first();
    }

    public function findByName(string $name, string $guard): ?Role
    {
        return $this->query()->where('guard_name', $guard)->where('name', $name)->first();
    }

    public function usersCount(Role $role): int
    {
        return DB::table((string) config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', $this->userMorphClass($role->guard_name))
            ->count();
    }

    public function options(string $guard): array
    {
        $options = [];

        foreach ($this->query()->where('guard_name', $guard)->orderBy('name')->pluck('name') as $name) {
            $options[(string) $name] = (string) Ui::roleLabel((string) $name);
        }

        return $options;
    }

    public function countForGuard(string $guard): int
    {
        return $this->query()->where('guard_name', $guard)->count();
    }

    public function ensurePermissions(array $names, string $guard): int
    {
        $existing = Permission::query()->where('guard_name', $guard)->whereIn('name', $names)->pluck('name')->all();

        foreach ($names as $name) {
            Permission::findOrCreate($name, $guard);
        }

        return count(array_diff(array_unique($names), $existing));
    }

    public function syncRole(string $name, string $guard, array $permissions): int
    {
        $role = Role::findOrCreate($name, $guard);
        $held = $role->permissions()->pluck('name')->all();
        $role->syncPermissions($permissions);

        return count(array_diff($permissions, $held));
    }

    private function userMorphClass(string $guard): string
    {
        return match ($guard) {
            Guard::Affiliate->value => (new Affiliate)->getMorphClass(),
            default => (new Admin)->getMorphClass(),
        };
    }
}
