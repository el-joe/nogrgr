<?php

namespace App\Modules\Localization\Services\StaticTexts;

use App\Modules\Localization\Jobs\SyncTenantStaticTexts;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;

/**
 * Queues SyncTenantStaticTexts for every provisioned store (deploy command, admin edits
 * of the storefront base set).
 */
class StaticTextDispatcher
{
    public const QUEUE = 'default';

    public function __construct(private readonly TenantRepositoryContract $tenants) {}

    /**
     * @param  list<int>|null  $ids
     */
    public function dispatchAll(?array $ids = null): int
    {
        $count = 0;

        $this->tenants->query()
            ->whereNotNull('provisioned_at')
            ->when($ids !== null, fn ($query) => $query->whereKey($ids))
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($tenants) use (&$count): void {
                foreach ($tenants as $tenant) {
                    SyncTenantStaticTexts::dispatch((int) $tenant->getKey())->onQueue(self::QUEUE);
                    $count++;
                }
            });

        return $count;
    }
}
