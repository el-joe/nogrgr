<?php

namespace App\Modules\Identity\DTOs;

/**
 * Outcome of `permissions:sync` (QA-P5-019): permissions created and permissions newly
 * granted to the synced system roles, centrally and per store.
 */
final class PermissionSyncReport
{
    public int $centralCreated = 0;

    public int $centralGranted = 0;

    public int $stores = 0;

    public int $storeCreated = 0;

    public int $storeGranted = 0;

    /** @var array<int, string> store id => error message */
    public array $failures = [];

    public function add(PermissionSyncResult $result): void
    {
        $this->storeCreated += $result->created;
        $this->storeGranted += $result->granted;
        $this->stores++;
    }
}
