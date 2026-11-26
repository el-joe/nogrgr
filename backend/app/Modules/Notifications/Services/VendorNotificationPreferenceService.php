<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\DTOs\VendorNotificationPreferencesData;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use App\Modules\Notifications\Repositories\Contracts\VendorNotificationPreferenceRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;

/**
 * Topic × channel switches of a store member (VEN-087), stored in the store database.
 * Missing rows use VendorNotificationTopic::defaultEnabled(); delivery is filtered by
 * EnforceVendorNotificationPreferences through allows().
 */
class VendorNotificationPreferenceService extends BaseService
{
    public function __construct(
        private readonly VendorNotificationPreferenceRepositoryContract $preferences,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return array<string, array<string, bool>> topic => channel => enabled
     */
    public function matrix(VendorStaff $staff): array
    {
        $stored = [];

        foreach ($this->preferences->forStaff((int) $staff->getKey()) as $preference) {
            $stored[$preference->topic->value][$preference->channel->value] = $preference->enabled;
        }

        $matrix = [];

        foreach (VendorNotificationTopic::cases() as $topic) {
            foreach (NotificationChannel::cases() as $channel) {
                $matrix[$topic->value][$channel->value] = $stored[$topic->value][$channel->value] ?? $topic->defaultEnabled($channel);
            }
        }

        return $matrix;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function update(VendorStaff $staff, VendorNotificationPreferencesData $data): array
    {
        $before = $this->matrix($staff);

        $this->transaction(function () use ($staff, $data, $before): void {
            foreach (VendorNotificationTopic::cases() as $topic) {
                foreach (NotificationChannel::cases() as $channel) {
                    $enabled = $data->enabled($topic, $channel);

                    if ($enabled !== $before[$topic->value][$channel->value]) {
                        $this->preferences->put((int) $staff->getKey(), $topic, $channel, $enabled);
                    }
                }
            }
        }, $this->preferences->query()->getModel()->getConnectionName());

        $after = $this->matrix($staff);

        if ($after !== $before) {
            $this->auditor->record('notification_preferences.updated', $staff, ['before' => $before, 'after' => $after]);
        }

        return $after;
    }

    /**
     * Whether a notification of this database type ("<category>.<event>") may go out
     * on this channel to the member.
     */
    public function allows(VendorStaff $staff, string $type, NotificationChannel $channel): bool
    {
        return $this->matrix($staff)[VendorNotificationTopic::fromType($type)->value][$channel->value];
    }
}
