<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Notifications\VendorProvisionedNotification;
use App\Modules\Platform\Notifications\VendorWelcomeNotification;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Support\TenantSlug;
use Illuminate\Support\Facades\Notification;

/**
 * Store is live: welcome e-mail to the owner + admin notification (vendors.view).
 */
class WelcomeProvisionedVendor
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
    ) {}

    public function handle(TenantProvisioned $event): void
    {
        $tenant = $event->tenant;
        $payload = $tenant->getAttribute(TenantProvisioningService::PAYLOAD);

        if (is_array($payload) && isset($payload['legacy_id'])) {
            return; // migrated from the old system (legacy:migrate): the store is not new, no welcome
        }

        if ($tenant->owner_email !== null) {
            Notification::route('mail', $tenant->owner_email)->notify(new VendorWelcomeNotification(
                $tenant->name,
                'https://'.TenantSlug::subdomainHost($tenant->slug),
                route('central.tenant.login'),
            ));
        }

        $admins = $this->admins->activeWithPermission('vendors.view');

        if ($admins->isNotEmpty()) {
            $this->dispatcher->send($admins, new VendorProvisionedNotification((int) $tenant->id, $tenant->name));
        }
    }
}
