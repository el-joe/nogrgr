<?php

namespace App\Modules\Billing\Services\Finance;

use App\Modules\Billing\Notifications\FinanceAlertNotification;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a finance alert to the active owners of a store (their accounts live in the
 * store database, so the lookup runs in its tenant context).
 */
class StoreFinanceNotifier
{
    public function __construct(
        private readonly TenantRunner $tenants,
        private readonly VendorStaffRepositoryContract $staff,
    ) {}

    /**
     * @param  array<string, string|int>  $params
     */
    public function notify(int $tenantId, string $event, array $params, ?string $routeName = null): void
    {
        try {
            $this->tenants->run($tenantId, function () use ($event, $params, $routeName): void {
                $owners = $this->staff->activeOwners();

                if ($owners->isNotEmpty()) {
                    $link = $routeName !== null && function_exists('vendor_route') ? rescue(static fn (): string => vendor_route($routeName), null, false) : null;
                    Notification::send($owners, new FinanceAlertNotification($event, $params, $link));
                }
            });
        } catch (ModelNotFoundException) {
            // The store was deleted; nothing to notify.
        }
    }
}
