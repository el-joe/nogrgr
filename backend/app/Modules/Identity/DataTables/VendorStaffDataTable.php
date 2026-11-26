<?php

namespace App\Modules\Identity\DataTables;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Identity\Services\VendorStaffService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Settings › Staff (VEN-076, Figma 90:115176), tenant DB, server-side:
 * member (name + e-mail), role, status, last sign-in, actions. Filters: search
 * (name, e-mail, phone), role, status.
 *
 * @extends BaseDataTable<VendorStaff>
 */
final class VendorStaffDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'role', 'status'];

    public function __construct(
        private readonly VendorStaffRepositoryContract $staff,
        private readonly VendorStaffService $service,
    ) {}

    protected function query(): Builder
    {
        return $this->staff->listQuery();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = AccountStatus::tryFrom((string) ($filters['status'] ?? ''));
        $role = is_string($filters['role'] ?? null) && $filters['role'] !== '' ? $filters['role'] : null;
        $search = is_string($filters['search'] ?? null) && trim($filters['search']) !== '' ? trim($filters['search']) : null;

        return $query
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->when($role !== null, fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)->where('guard_name', Guard::Vendor->value)))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $term = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $actor = auth('vendor')->user();
        $canManage = $actor instanceof VendorStaff && $actor->can('settings.staff.manage');
        $cell = fn (VendorStaff $member): array => [
            'member' => $member,
            'actor' => $actor,
            'locked' => $this->service->isLocked($member),
            'canManage' => $canManage,
        ];

        return $table
            ->filter(static fn (): null => null)
            ->addColumn('member', fn (VendorStaff $member): string => view('identity::vendor.staff._member-cell', $cell($member))->render())
            ->orderColumn('member', static fn (Builder $query, string $order) => $query->orderBy('name', $order === 'desc' ? 'desc' : 'asc'))
            ->addColumn('role', fn (VendorStaff $member): string => view('identity::vendor.staff._role-cell', $cell($member))->render())
            ->editColumn('last_login_at', fn (VendorStaff $member): string => $member->last_login_at !== null
                ? '<time datetime="'.e($member->last_login_at->toIso8601String()).'" title="'.e($member->last_login_at->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm')).'">'.e($member->last_login_at->locale(app()->getLocale())->diffForHumans()).'</time>'
                : '<span class="text-ink-muted">'.e(__('identity::staff.never_signed_in')).'</span>')
            ->editColumn('status', fn (VendorStaff $member): string => view('identity::vendor.staff._status-cell', $cell($member))->render())
            ->addColumn('actions', fn (VendorStaff $member): string => view('identity::vendor.staff._actions-cell', $cell($member))->render())
            ->rawColumns(['member', 'role', 'last_login_at', 'status', 'actions']);
    }
}
