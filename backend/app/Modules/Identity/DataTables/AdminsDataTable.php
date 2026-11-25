<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › System › Admins list (server-side). Filters: search (name, email), role,
 * status.
 *
 * @extends BaseDataTable<Admin>
 */
final class AdminsDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'role', 'status'];

    public function __construct(private readonly AdminRepositoryContract $admins) {}

    protected function query(): Builder
    {
        return $this->admins->query()->with('roles');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = AccountStatus::tryFrom((string) ($filters['status'] ?? ''));
        $role = is_string($filters['role'] ?? null) && $filters['role'] !== '' ? $filters['role'] : null;
        $search = is_string($filters['search'] ?? null) && $filters['search'] !== '' ? $filters['search'] : null;

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->when($role !== null, fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)->where('guard_name', 'admin')))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $actor = auth('admin')->user();

        return $table
            ->filter(static fn (): null => null)
            ->addColumn('admin', fn (Admin $admin): string => view('identity::admin.admins._admin-cell', ['admin' => $admin, 'actor' => $actor])->render())
            ->orderColumn('admin', static fn (Builder $query, string $order) => $query->orderBy('name', $order === 'desc' ? 'desc' : 'asc'))
            ->addColumn('role', fn (Admin $admin): string => view('identity::admin.admins._role-cell', ['admin' => $admin])->render())
            ->editColumn('last_login_at', fn (Admin $admin): string => $admin->last_login_at !== null
                ? '<time datetime="'.e($admin->last_login_at->toIso8601String()).'" title="'.e($admin->last_login_at->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm')).'">'.e($admin->last_login_at->locale(app()->getLocale())->diffForHumans()).'</time>'
                : '<span class="text-ink-muted">'.e(__('identity::admins.never_signed_in')).'</span>')
            ->editColumn('status', fn (Admin $admin): string => view('identity::admin.admins._status-cell', ['admin' => $admin, 'actor' => $actor])->render())
            ->addColumn('actions', fn (Admin $admin): string => view('identity::admin.admins._actions-cell', ['admin' => $admin, 'actor' => $actor])->render())
            ->rawColumns(['admin', 'role', 'last_login_at', 'status', 'actions']);
    }
}
