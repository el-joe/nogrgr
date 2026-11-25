<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Admin>
 */
class AdminRepository extends BaseRepository implements AdminRepositoryContract
{
    protected function model(): string
    {
        return Admin::class;
    }

    public function countActiveWithRole(string $role): int
    {
        return $this->query()->role($role, 'admin')->where('status', AccountStatus::Active->value)->count();
    }

    public function activeWithRole(string $role): Collection
    {
        return $this->query()->role($role, 'admin')->where('status', AccountStatus::Active->value)->get();
    }

    public function activeWithPermission(string $permission): Collection
    {
        return $this->query()
            ->where('status', AccountStatus::Active->value)
            ->get()
            ->filter(fn (Admin $admin): bool => $admin->can($permission))
            ->values();
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->query()->orderBy('name')->get(['id', 'name', 'email']) as $admin) {
            $options[$admin->id] = $admin->name.' ('.$admin->email.')';
        }

        return $options;
    }

    public function counts(): array
    {
        $byStatus = $this->query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'all' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[AccountStatus::Active->value] ?? 0),
            'suspended' => (int) ($byStatus[AccountStatus::Suspended->value] ?? 0),
            'pending' => (int) ($byStatus[AccountStatus::Pending->value] ?? 0),
        ];
    }
}
