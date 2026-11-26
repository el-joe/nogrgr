<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Events\ComplianceSubmitted;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Notifications\ComplianceSubmittedNotification;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;

/**
 * A store submitted its compliance center: admins allowed to decide KYC
 * (vendors.kyc.manage) get it in their inbox, by e-mail and live (per preferences).
 * The event fires inside the store's tenant context; admins live centrally.
 */
class NotifyAdminsOfComplianceSubmission
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    public function handle(ComplianceSubmitted $event): void
    {
        $send = function () use ($event): void {
            $admins = $this->admins->activeWithPermission('vendors.kyc.manage');

            if ($admins->isEmpty()) {
                return;
            }

            $tenant = $this->tenants->find($event->profile->tenant_id);
            $store = $tenant instanceof Tenant ? (string) $tenant->name : (string) $event->profile->legal_name;

            $this->dispatcher->send($admins, new ComplianceSubmittedNotification((int) $event->profile->id, $store, $event->resubmitted));
        };

        tenancy()->initialized ? tenancy()->central($send) : $send();
    }
}
