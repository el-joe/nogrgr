<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\PlanLimitOverridesData;
use App\Modules\Billing\Repositories\Contracts\PlanLimitOverrideRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;

/**
 * Admin › Vendor profile › Limit overrides (ADM-041h): replaces the store's override set
 * and records the before / after in the audit log ("vendors.limits_updated").
 */
class PlanLimitOverrideService extends BaseService
{
    public const AUDIT_ACTION = 'vendors.limits_updated';

    public function __construct(
        private readonly PlanLimitOverrideRepositoryContract $overrides,
        private readonly Auditor $auditor,
    ) {}

    public function save(Tenant $tenant, PlanLimitOverridesData $data): void
    {
        $tenantId = (int) $tenant->getKey();
        $before = $this->overrides->valuesFor($tenantId);
        ksort($before);
        $after = $data->values;
        ksort($after);

        $this->overrides->replaceFor($tenantId, $after);

        if ($before !== $after) {
            $this->auditor->record(self::AUDIT_ACTION, $tenant, ['before' => $before, 'after' => $after]);
        }
    }
}
