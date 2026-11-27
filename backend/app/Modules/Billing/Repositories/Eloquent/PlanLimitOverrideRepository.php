<?php

namespace App\Modules\Billing\Repositories\Eloquent;

use App\Modules\Billing\Models\PlanLimitOverride;
use App\Modules\Billing\Repositories\Contracts\PlanLimitOverrideRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<PlanLimitOverride>
 */
class PlanLimitOverrideRepository extends BaseRepository implements PlanLimitOverrideRepositoryContract
{
    protected function model(): string
    {
        return PlanLimitOverride::class;
    }

    public function valuesFor(int $tenantId): array
    {
        $values = [];

        foreach (PlanLimitOverride::query()->forTenant($tenantId)->get(['limit', 'value']) as $override) {
            $values[$override->limit->value] = $override->value;
        }

        return $values;
    }

    public function replaceFor(int $tenantId, array $values): void
    {
        DB::connection((new PlanLimitOverride)->getConnectionName())->transaction(function () use ($tenantId, $values): void {
            PlanLimitOverride::query()->forTenant($tenantId)->whereNotIn('limit', array_keys($values))->delete();

            foreach ($values as $limit => $value) {
                PlanLimitOverride::query()->forTenant($tenantId)->updateOrCreate(
                    ['tenant_id' => $tenantId, 'limit' => $limit],
                    ['value' => $value],
                );
            }
        });
    }
}
