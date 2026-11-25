<?php

namespace App\Modules\Notifications\Repositories\Contracts;

use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RepositoryContract<PanelNotificationRecord>
 */
interface PanelNotificationRepositoryContract extends RepositoryContract
{
    /**
     * @return Builder<PanelNotificationRecord>
     */
    public function queryFor(Model $notifiable): Builder;

    public function findFor(Model $notifiable, string $id): ?PanelNotificationRecord;

    public function unreadCount(Model $notifiable): int;

    /**
     * Unread count per category prefix of the type ("orders" => 3).
     *
     * @return array<string, int>
     */
    public function unreadByCategory(Model $notifiable): array;

    /**
     * @return Collection<int, PanelNotificationRecord>
     */
    public function latest(Model $notifiable, int $limit): Collection;

    public function markAllRead(Model $notifiable): int;
}
