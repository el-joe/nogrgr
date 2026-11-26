<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use App\Modules\Notifications\Models\VendorNotificationPreference;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Store members' notification switches (store database).
 *
 * @extends RepositoryContract<VendorNotificationPreference>
 */
interface VendorNotificationPreferenceRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, VendorNotificationPreference>
     */
    public function forStaff(int $staffId): Collection;

    public function put(int $staffId, VendorNotificationTopic $topic, NotificationChannel $channel, bool $enabled): VendorNotificationPreference;
}
