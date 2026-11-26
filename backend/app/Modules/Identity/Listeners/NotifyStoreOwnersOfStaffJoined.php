<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Events\VendorStaffJoined;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Notifications\VendorTeamMemberJoinedNotification;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Shared\Support\Ui;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the store's active owners (except the new member) that an invitation was accepted.
 */
class NotifyStoreOwnersOfStaffJoined
{
    public function __construct(private readonly VendorStaffRepositoryContract $staff) {}

    public function handle(VendorStaffJoined $event): void
    {
        if (! tenancy()->initialized || (int) tenant()?->getTenantKey() !== $event->tenantId) {
            return;
        }

        $member = $this->staff->find($event->staffId);

        if (! $member instanceof VendorStaff) {
            return;
        }

        $owners = $this->staff->activeOwners()->reject(static fn (VendorStaff $owner): bool => $owner->is($member));

        if ($owners->isNotEmpty()) {
            Notification::send($owners, new VendorTeamMemberJoinedNotification(
                $member->name,
                (string) Ui::roleLabel($member->getRoleNames()->first()),
                vendor_route('settings.staff.index'),
            ));
        }
    }
}
