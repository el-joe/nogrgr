<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\OwnerInboxNotification;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Notifications\Services\OwnerInboxService;
use App\Modules\Platform\Events\DomainDnsFailed;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Notifications\DomainAlertNotification;
use App\Modules\Platform\Notifications\DomainDnsFailedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * DNS of a custom domain went missing (decision #124): the store owner gets the
 * `vendor.domain_dns_failed` e-mail and an owner-portal inbox copy; admins with
 * vendors.domains.view an inbox alert.
 */
class NotifyOfDomainDnsFailed
{
    public function __construct(
        private readonly OwnerInboxService $ownerInbox,
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
    ) {}

    public function handle(DomainDnsFailed $event): void
    {
        $tenant = $event->request->tenant()->withoutGlobalScopes()->first();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $params = ['domain' => (string) $event->request->host, 'store' => (string) $tenant->name];

        if (filled($tenant->owner_email)) {
            Notification::route('mail', (string) $tenant->owner_email)->notify(new DomainDnsFailedNotification(
                (string) ($tenant->owner_name ?? ''),
                (string) $tenant->name,
                (string) $event->request->host,
                route('central.tenant.login'),
            ));
        }

        $this->ownerInbox->send($tenant, new OwnerInboxNotification(
            NotificationCategory::Vendors,
            'domain_dns_failed',
            'platform::notifications.owner_domain_dns_failed.title',
            'platform::notifications.owner_domain_dns_failed.body',
            $params,
            rescue(static fn (): string => route('owner.domains.index'), null, false),
        ));

        $admins = $this->admins->activeWithPermission('vendors.domains.view');

        if ($admins->isNotEmpty()) {
            $this->dispatcher->send($admins, new DomainAlertNotification('admin_domain_dns_failed', $params, rescue(static fn (): string => route('admin.vendors.domains.index'), null, false)));
        }
    }
}
