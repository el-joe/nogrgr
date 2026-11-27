<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Notifications\OwnerInboxNotification;
use App\Modules\Notifications\Services\OwnerInboxService;
use App\Modules\Platform\Events\ComplianceReviewed;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Notifications\ComplianceActionNotification;
use Illuminate\Support\Facades\Notification;

/**
 * E-mails the store owner the compliance decision and the admin's note
 * (`vendor.compliance_decision`) and copies it into the owner-portal inbox (#124).
 */
class NotifyOwnerOfComplianceDecision
{
    public function __construct(private readonly OwnerInboxService $ownerInbox) {}

    public function handle(ComplianceReviewed $event): void
    {
        $profile = $event->profile;
        /** @var Tenant|null $tenant */
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();

        if ($tenant === null) {
            return;
        }

        $this->ownerInbox->send($tenant, new OwnerInboxNotification(
            NotificationCategory::Vendors,
            'compliance_'.$profile->status->value,
            'platform::notifications.owner_compliance.title',
            'platform::notifications.owner_compliance.body',
            ['store' => (string) $tenant->name, 'status' => $profile->status->label()],
            rescue(static fn (): string => route('owner.dashboard'), null, false),
        ));

        if ($tenant->owner_email === null) {
            return;
        }

        Notification::route('mail', $tenant->owner_email)->notify(new ComplianceActionNotification(
            $tenant->name,
            $profile->status->value,
            $profile->decision_note,
        ));
    }
}
