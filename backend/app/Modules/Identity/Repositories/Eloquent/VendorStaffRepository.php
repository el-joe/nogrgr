<?php

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VendorStaff>
 */
class VendorStaffRepository extends BaseRepository implements VendorStaffRepositoryContract
{
    protected function model(): string
    {
        return VendorStaff::class;
    }

    public function firstActiveOwner(): ?VendorStaff
    {
        return $this->query()
            ->where('is_owner', true)
            ->where('status', AccountStatus::Active)
            ->orderBy('id')
            ->first();
    }

    public function activeOwners(): Collection
    {
        return $this->query()
            ->where('is_owner', true)
            ->where('status', AccountStatus::Active)
            ->orderBy('id')
            ->get();
    }

    public function activeWithPermission(string $permission): Collection
    {
        return $this->query()
            ->where('status', AccountStatus::Active)
            ->orderBy('id')
            ->get()
            ->filter(static fn (VendorStaff $staff): bool => $staff->can($permission))
            ->values();
    }

    public function listQuery(): Builder
    {
        return $this->query()->with('roles');
    }

    public function counts(): array
    {
        $rows = $this->query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'all' => (int) $rows->sum(),
            'active' => (int) ($rows[AccountStatus::Active->value] ?? 0),
            'pending' => (int) ($rows[AccountStatus::Pending->value] ?? 0),
            'suspended' => (int) ($rows[AccountStatus::Suspended->value] ?? 0),
        ];
    }

    public function emailTaken(string $email, ?int $ignoreId = null): bool
    {
        return $this->query()
            ->where('email', mb_strtolower($email))
            ->when($ignoreId !== null, static fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
