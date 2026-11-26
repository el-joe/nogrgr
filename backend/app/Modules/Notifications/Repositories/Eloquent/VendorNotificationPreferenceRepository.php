<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use App\Modules\Notifications\Models\VendorNotificationPreference;
use App\Modules\Notifications\Repositories\Contracts\VendorNotificationPreferenceRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VendorNotificationPreference>
 */
class VendorNotificationPreferenceRepository extends BaseRepository implements VendorNotificationPreferenceRepositoryContract
{
    protected function model(): string
    {
        return VendorNotificationPreference::class;
    }

    public function forStaff(int $staffId): Collection
    {
        return $this->query()->where('vendor_staff_id', $staffId)->get();
    }

    public function put(int $staffId, VendorNotificationTopic $topic, NotificationChannel $channel, bool $enabled): VendorNotificationPreference
    {
        /** @var VendorNotificationPreference $preference */
        $preference = $this->query()->updateOrCreate(
            ['vendor_staff_id' => $staffId, 'topic' => $topic->value, 'channel' => $channel->value],
            ['enabled' => $enabled],
        );

        return $preference;
    }
}
