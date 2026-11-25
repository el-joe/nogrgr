<?php

namespace App\Modules\Catalog\Jobs\Requests;

use App\Modules\Catalog\Notifications\StoreRequestNotification;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers a requests-center update to the store's staff allowed to see it (vendor bell,
 * inbox and live push). Runs inside the store's tenant context because staff and their
 * notifications live in the tenant database.
 */
class NotifyStoreStaff implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, string|int>  $parameters
     * @param  array<string, int|string>  $routeParameters
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $permission,
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly string $vendorRoute,
        public readonly array $routeParameters = [],
    ) {
        $this->onQueue('notifications');
    }

    public function handle(TenantRepositoryContract $tenants): void
    {
        $tenant = $tenants->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        // A store whose database is not provisioned (yet) has no staff to notify.
        $database = $tenant->database();

        if (! $database->manager()->databaseExists($database->getName())) {
            return;
        }

        $tenant->run(function (): void {
            $staff = app(VendorStaffRepositoryContract::class)->activeWithPermission($this->permission);

            if ($staff->isNotEmpty()) {
                Notification::sendNow($staff, new StoreRequestNotification($this->eventName, $this->parameters, $this->vendorRoute, $this->routeParameters));
            }
        });
    }
}
