<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Repositories\Contracts\RoleRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › System › Roles list (server-side): name, admins assigned, permissions,
 * last change. Filter: search by name.
 *
 * @extends BaseDataTable<Role>
 */
final class RolesDataTable extends BaseDataTable
{
    public const FILTERS = ['search'];

    public function __construct(private readonly RoleRepositoryContract $roles) {}

    protected function query(): Builder
    {
        return $this->roles->listQuery('admin');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) && $filters['search'] !== '' ? $filters['search'] : null;

        return $query->when($search !== null, fn (Builder $query) => $query->where('roles.name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->editColumn('name', fn (Role $role): string => view('identity::admin.roles._name-cell', ['role' => $role, 'locked' => SystemRole::isLocked($role->name)])->render())
            ->orderColumn('users_count', static fn (Builder $query, string $order) => $query->orderBy('users_count', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('permissions_count', static fn (Builder $query, string $order) => $query->orderBy('permissions_count', $order === 'desc' ? 'desc' : 'asc'))
            ->editColumn('users_count', fn (Role $role): string => e(trans_choice('identity::roles.admins_count', (int) $role->getAttribute('users_count'), ['count' => (int) $role->getAttribute('users_count')])))
            ->editColumn('permissions_count', fn (Role $role): string => SystemRole::isLocked($role->name)
                ? e(__('identity::roles.all_permissions'))
                : e(trans_choice('identity::roles.permissions_count', (int) $role->getAttribute('permissions_count'), ['count' => (int) $role->getAttribute('permissions_count')])))
            ->editColumn('updated_at', fn (Role $role): string => $role->updated_at !== null
                ? '<time datetime="'.e($role->updated_at->toIso8601String()).'">'.e($role->updated_at->locale(app()->getLocale())->isoFormat('D MMM YYYY')).'</time>'
                : '')
            ->addColumn('actions', fn (Role $role): string => view('identity::admin.roles._actions-cell', ['role' => $role, 'locked' => SystemRole::isLocked($role->name), 'assigned' => (int) $role->getAttribute('users_count')])->render())
            ->rawColumns(['name', 'updated_at', 'actions']);
    }
}
