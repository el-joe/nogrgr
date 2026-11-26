<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\RoleRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Settings › Roles & permissions (VEN-077), tenant DB, server-side: role,
 * staff assigned, permissions, last change, actions. The built-in "owner" role is
 * locked (view only). Filter: search by name.
 *
 * @extends BaseDataTable<Role>
 */
final class VendorRolesDataTable extends BaseDataTable
{
    public const FILTERS = ['search'];

    public function __construct(private readonly RoleRepositoryContract $roles) {}

    protected function query(): Builder
    {
        return $this->roles->listQuery(Guard::Vendor->value);
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $search = is_string($filters['search'] ?? null) && $filters['search'] !== '' ? $filters['search'] : null;

        return $query->when($search !== null, fn (Builder $query) => $query->where('roles.name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'));
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $actor = auth('vendor')->user();
        $canManage = $actor instanceof VendorStaff && $actor->can('settings.roles.manage');
        $locked = static fn (Role $role): bool => SystemRole::lockedFor($role->name, Guard::Vendor->value);

        return $table
            ->filter(static fn (): null => null)
            ->editColumn('name', fn (Role $role): string => view('identity::vendor.roles._name-cell', ['role' => $role, 'locked' => $locked($role)])->render())
            ->orderColumn('users_count', static fn (Builder $query, string $order) => $query->orderBy('users_count', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('permissions_count', static fn (Builder $query, string $order) => $query->orderBy('permissions_count', $order === 'desc' ? 'desc' : 'asc'))
            ->editColumn('users_count', fn (Role $role): string => e(trans_choice('identity::staff.roles.staff_count', (int) $role->getAttribute('users_count'), ['count' => (int) $role->getAttribute('users_count')])))
            ->editColumn('permissions_count', fn (Role $role): string => $locked($role)
                ? e(__('identity::roles.all_permissions'))
                : e(trans_choice('identity::roles.permissions_count', (int) $role->getAttribute('permissions_count'), ['count' => (int) $role->getAttribute('permissions_count')])))
            ->editColumn('updated_at', fn (Role $role): string => $role->updated_at !== null
                ? '<time datetime="'.e($role->updated_at->toIso8601String()).'">'.e($role->updated_at->locale(app()->getLocale())->isoFormat('D MMM YYYY')).'</time>'
                : '')
            ->addColumn('actions', fn (Role $role): string => view('identity::vendor.roles._actions-cell', ['role' => $role, 'locked' => $locked($role), 'assigned' => (int) $role->getAttribute('users_count'), 'canManage' => $canManage])->render())
            ->rawColumns(['name', 'updated_at', 'actions']);
    }
}
