<?php

namespace App\Modules\Support\Jobs;

use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Support\Notifications\StoreSupportNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers a support ticket update to the store's staff allowed to see support tickets
 * (vendor bell, inbox, live push; e-mail for the staff member who opened the ticket).
 * Runs in the store's tenant context: staff and their notifications live there.
 */
class NotifySupportStaff implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $ticketId,
        public readonly string $eventName,
        public readonly array $parameters,
        public readonly ?int $mailStaffId = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(TenantRepositoryContract $tenants): void
    {
        $tenant = $tenants->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $database = $tenant->database();

        if (! $database->manager()->databaseExists($database->getName())) {
            return;
        }

        $tenant->run(function (): void {
            $staff = app(VendorStaffRepositoryContract::class)->activeWithPermission('support.view');

            if ($staff->isEmpty()) {
                return;
            }

            Notification::sendNow($staff, new StoreSupportNotification(
                $this->eventName,
                $this->parameters,
                'support.show',
                ['ticket' => $this->ticketId],
                $this->mailStaffId,
                vendor_route('support.show', ['ticket' => $this->ticketId]),
            ));
        });
    }
}
