<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Repositories\Contracts\StockReservationRepositoryContract;
use App\Modules\Commerce\Services\Orders\OrderLifecycleService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Payment timeout (scheduled every 5 minutes): the central stock holds tell which stores
 * have unpaid checkouts past their deadline; each one is cancelled in its store and the
 * holds are released.
 */
class ExpireUnpaidOrders implements ShouldQueue
{
    use Queueable;

    public function handle(TenantRepositoryContract $tenants, StockReservationRepositoryContract $reservations): void
    {
        foreach ($reservations->expiredGroupsByTenant() as $tenantId => $groups) {
            $tenant = $tenants->find((int) $tenantId);

            if (! $tenant instanceof Tenant) {
                continue;
            }

            $tenant->run(static function () use ($groups): void {
                $lifecycle = app(OrderLifecycleService::class);

                foreach ($groups as $group) {
                    $lifecycle->expireGroup($group);
                }
            });
        }
    }
}
