<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<VendorRegistration>
 */
class VendorRegistrationRepository extends BaseRepository implements VendorRegistrationRepositoryContract
{
    protected function model(): string
    {
        return VendorRegistration::class;
    }

    public function query(): Builder
    {
        return VendorRegistration::query()->withoutGlobalScopes();
    }

    public function findByTokenHash(string $hash): ?VendorRegistration
    {
        return $this->query()->where('token_hash', $hash)->first();
    }

    public function pending(array $filters = []): Builder
    {
        $status = RegistrationStatus::tryFrom((string) ($filters['status'] ?? ''));
        $planId = filter_var($filters['plan_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $this->query()
            ->select('vendor_registrations.*')
            ->with('plan:id,name')
            ->where('status', '!=', RegistrationStatus::Completed->value)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->when($planId !== false && $planId !== null, fn (Builder $query) => $query->where('plan_id', $planId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('email', 'like', $term)
                    ->orWhere('owner_name', 'like', $term)
                    ->orWhere('store_name', 'like', $term)
                    ->orWhere('slug', 'like', $term));
            });
    }

    public function countPending(): int
    {
        return $this->query()->where('status', '!=', RegistrationStatus::Completed->value)->count();
    }

    public function slugReserved(string $slug, ?int $exceptId = null): bool
    {
        return $this->query()
            ->where('slug', strtolower($slug))
            ->where('status', '!=', RegistrationStatus::Completed->value)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }
}
