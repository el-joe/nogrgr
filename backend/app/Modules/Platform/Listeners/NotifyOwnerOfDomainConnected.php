<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Platform\Events\DomainConnected;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Notifications\DomainConnectedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * MAIL-032: e-mails the store owner when a custom domain is verified and connected.
 */
class NotifyOwnerOfDomainConnected
{
    public function handle(DomainConnected $event): void
    {
        $tenant = $event->request->tenant;

        if (! $tenant instanceof Tenant || blank($tenant->owner_email)) {
            return;
        }

        Notification::route('mail', $tenant->owner_email)->notify(new DomainConnectedNotification(
            (string) ($tenant->owner_name ?? ''),
            (string) $tenant->name,
            (string) $event->request->host,
            route('central.tenant.login'),
        ));
    }
}
