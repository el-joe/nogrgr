<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * @extends BaseRepository<Tenant>
 */
class TenantRepository extends BaseRepository implements TenantRepositoryContract
{
    protected function model(): string
    {
        return Tenant::class;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return $this->query()->where('slug', strtolower($slug))->first();
    }

    /**
     * @param  list<int>  $ids
     */
    public function findMany(array $ids): TenantCollection
    {
        if ($ids === []) {
            return new TenantCollection;
        }

        return $this->query()->whereKey($ids)->orderBy('name')->get();
    }

    public function countByStatus(TenantStatus $status): int
    {
        return $this->query()->where('status', $status->value)->count();
    }
}
