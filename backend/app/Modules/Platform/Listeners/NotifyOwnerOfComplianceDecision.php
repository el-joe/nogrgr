<?php

namespace App\Modules\Platform\Listeners;

use App\Modules\Platform\Events\ComplianceReviewed;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Notifications\ComplianceActionNotification;
use Illuminate\Support\Facades\Notification;

/**
 * E-mails the store owner the compliance decision and the admin's note.
 */
class NotifyOwnerOfComplianceDecision
{
    public function handle(ComplianceReviewed $event): void
    {
        $profile = $event->profile;
        /** @var Tenant|null $tenant */
        $tenant = $profile->tenant()->withoutGlobalScopes()->first();

        if ($tenant === null || $tenant->owner_email === null) {
            return;
        }

        Notification::route('mail', $tenant->owner_email)->notify(new ComplianceActionNotification(
            $tenant->name,
            $profile->status->value,
            $profile->decision_note,
        ));
    }
}
