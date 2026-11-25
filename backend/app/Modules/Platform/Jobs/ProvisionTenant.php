<?php

namespace App\Modules\Platform\Jobs;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs TenantProvisioningService::run() for one store. Single attempt: a failure is
 * recorded on the tenant ("failed") and retried by an admin, never silently re-run.
 * Concurrent runs are prevented by the status guard in TenantProvisioningService::canRun().
 */
class ProvisionTenant implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $tenantId) {}

    public function handle(TenantRepositoryContract $tenants, TenantProvisioningService $provisioning): void
    {
        $tenant = $tenants->find($this->tenantId);

        if ($tenant instanceof Tenant) {
            $provisioning->run($tenant);
        }
    }
}
