<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Models\CacheClearRun;
use App\Modules\Platform\Repositories\Contracts\CacheClearRunRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<CacheClearRun>
 */
class CacheClearRunRepository extends BaseRepository implements CacheClearRunRepositoryContract
{
    protected function model(): string
    {
        return CacheClearRun::class;
    }

    public function latest(array $actions, ?int $tenantId = null): array
    {
        $ids = CacheClearRun::withoutTenantScope()
            ->whereIn('action', $actions)
            ->when($tenantId === null, fn ($query) => $query->whereNull('tenant_id'), fn ($query) => $query->where('tenant_id', $tenantId))
            ->selectRaw('MAX(id) as id')
            ->groupBy('action')
            ->pluck('id');

        $latest = [];

        foreach (CacheClearRun::withoutTenantScope()->with('admin:id,name')->whereIn('id', $ids)->get() as $run) {
            $latest[$run->action->value] = $run;
        }

        return $latest;
    }
}
