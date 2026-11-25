<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Repositories\Contracts\NotificationPreferenceRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends BaseRepository implements NotificationPreferenceRepositoryContract
{
    protected function model(): string
    {
        return NotificationPreference::class;
    }

    public function forNotifiable(Model $notifiable): Collection
    {
        return $this->query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->get();
    }

    public function put(Model $notifiable, NotificationCategory $category, NotificationChannel $channel, bool $enabled): NotificationPreference
    {
        /** @var NotificationPreference $preference */
        $preference = $this->query()->updateOrCreate([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'category' => $category->value,
            'channel' => $channel->value,
        ], ['enabled' => $enabled]);

        return $preference;
    }
}
