<?php

namespace App\Modules\Localization\Jobs;

use App\Modules\Localization\Services\StaticTexts\StaticTextSyncService;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Bring one store's static texts in step with the base set (new keys after a deploy,
 * changed base texts after an admin edit). Dispatched per store by
 * `localization:sync-static-texts` and by the admin editor.
 */
class SyncTenantStaticTexts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $tenantId) {}

    public function uniqueId(): string
    {
        return (string) $this->tenantId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(TenantRepositoryContract $tenants, StaticTextSyncService $sync): void
    {
        $tenant = $tenants->find($this->tenantId);

        if ($tenant === null || $tenant->getAttribute('provisioned_at') === null) {
            return;
        }

        $tenant->run(fn () => $sync->syncCurrentTenant());
    }
}
