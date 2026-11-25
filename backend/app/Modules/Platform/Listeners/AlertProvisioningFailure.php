<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Events\TenantProvisioningFailed;
use App\Modules\Platform\Notifications\ProvisioningFailedNotification;

/**
 * Provisioning failed: admins who can retry it (vendors.manage) are notified.
 */
class AlertProvisioningFailure
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
    ) {}

    public function handle(TenantProvisioningFailed $event): void
    {
        $admins = $this->admins->activeWithPermission('vendors.manage');

        if ($admins->isNotEmpty()) {
            $this->dispatcher->send($admins, new ProvisioningFailedNotification((int) $event->tenant->id, $event->tenant->name));
        }
    }
}
