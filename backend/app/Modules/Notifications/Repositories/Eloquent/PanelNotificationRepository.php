<?php

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Repositories\Contracts\PanelNotificationRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<PanelNotificationRecord>
 */
class PanelNotificationRepository extends BaseRepository implements PanelNotificationRepositoryContract
{
    protected function model(): string
    {
        return PanelNotificationRecord::class;
    }

    public function queryFor(Model $notifiable): Builder
    {
        return $this->query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey());
    }

    public function findFor(Model $notifiable, string $id): ?PanelNotificationRecord
    {
        return $this->queryFor($notifiable)->whereKey($id)->first();
    }

    public function unreadCount(Model $notifiable): int
    {
        return $this->queryFor($notifiable)->whereNull('read_at')->count();
    }

    public function unreadByCategory(Model $notifiable): array
    {
        $counts = [];

        foreach ($this->queryFor($notifiable)->whereNull('read_at')->pluck('type') as $type) {
            $category = strtok((string) $type, '.') ?: (string) $type;
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        return $counts;
    }

    public function latest(Model $notifiable, int $limit): Collection
    {
        return $this->queryFor($notifiable)->latest()->limit($limit)->get();
    }

    public function markAllRead(Model $notifiable): int
    {
        return $this->queryFor($notifiable)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
