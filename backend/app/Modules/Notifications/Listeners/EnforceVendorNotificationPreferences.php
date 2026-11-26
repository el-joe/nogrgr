<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Notifications\PanelNotification;
use App\Modules\Notifications\Services\VendorNotificationPreferenceService;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Last gate before a panel notification reaches a store member on one channel: the
 * member's topic × channel switch (VEN-087). Returning false cancels only that channel,
 * whatever the notification's own via() chose.
 */
class EnforceVendorNotificationPreferences
{
    public function __construct(private readonly VendorNotificationPreferenceService $preferences) {}

    /**
     * False cancels the channel; null lets the other listeners decide (events->until()).
     */
    public function handle(NotificationSending $event): ?bool
    {
        if (! $event->notifiable instanceof VendorStaff || ! $event->notification instanceof PanelNotification || ! tenancy()->initialized) {
            return null;
        }

        $channel = NotificationChannel::fromDriver($event->channel);

        if ($channel === null || $this->preferences->allows($event->notifiable, $event->notification->databaseType($event->notifiable), $channel)) {
            return null;
        }

        return false;
    }
}
