<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\DTOs\NotificationPreferencesData;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Repositories\Contracts\NotificationPreferenceRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/**
 * Category × channel switches of a central panel user (ADM-007a). Missing rows use
 * NotificationCategory::defaultEnabled(); PanelNotification::via() asks channelsFor().
 */
class NotificationPreferenceService extends BaseService
{
    public function __construct(
        private readonly NotificationPreferenceRepositoryContract $preferences,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return array<string, array<string, bool>> category => channel => enabled
     */
    public function matrix(Model $notifiable): array
    {
        $stored = [];

        foreach ($this->preferences->forNotifiable($notifiable) as $preference) {
            $stored[$preference->category->value][$preference->channel->value] = $preference->enabled;
        }

        $matrix = [];

        foreach (NotificationCategory::cases() as $category) {
            foreach (NotificationChannel::cases() as $channel) {
                $matrix[$category->value][$channel->value] = $stored[$category->value][$channel->value] ?? $category->defaultEnabled($channel);
            }
        }

        return $matrix;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function update(Model $notifiable, NotificationPreferencesData $data): array
    {
        $before = $this->matrix($notifiable);

        $this->transaction(function () use ($notifiable, $data, $before): void {
            foreach (NotificationCategory::cases() as $category) {
                foreach (NotificationChannel::cases() as $channel) {
                    $enabled = $data->enabled($category, $channel);

                    if ($enabled !== $before[$category->value][$channel->value]) {
                        $this->preferences->put($notifiable, $category, $channel, $enabled);
                    }
                }
            }
        }, $this->connection());

        $after = $this->matrix($notifiable);

        if ($after !== $before) {
            $this->auditor->record('notification_preferences.updated', $notifiable, ['before' => $before, 'after' => $after]);
        }

        return $after;
    }

    public function allows(Model $notifiable, NotificationCategory $category, NotificationChannel $channel): bool
    {
        return $this->matrix($notifiable)[$category->value][$channel->value];
    }

    /**
     * Laravel channel drivers enabled for a category.
     *
     * @return list<string>
     */
    public function channelsFor(Model $notifiable, NotificationCategory $category): array
    {
        $matrix = $this->matrix($notifiable)[$category->value];
        $channels = [];

        foreach (NotificationChannel::cases() as $channel) {
            if ($matrix[$channel->value]) {
                $channels[] = $channel->driver();
            }
        }

        return $channels;
    }

    private function connection(): ?string
    {
        return $this->preferences->query()->getModel()->getConnectionName();
    }
}
