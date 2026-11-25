<?php

namespace App\Modules\Notifications\Models;

/**
 * A database notification of a tenant user (vendor staff) — the store's own
 * `notifications` table, same payload contract as PanelNotificationRecord.
 */
class TenantNotificationRecord extends PanelNotificationRecord
{
    /**
     * The tenant connection (default while tenancy is initialised), not the central one.
     */
    public function getConnectionName(): ?string
    {
        return null;
    }
}
