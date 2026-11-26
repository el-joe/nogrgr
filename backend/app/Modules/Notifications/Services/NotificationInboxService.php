<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Repositories\Contracts\PanelNotificationRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;

/**
 * Inbox of a central panel user (ADM-007): unread counts, bell feed, open (mark read
 * + follow the link), mark read, mark all read, delete. A user only ever reaches
 * their own rows (findOwned() 404s on anyone else's id).
 */
class NotificationInboxService extends BaseService
{
    public const BELL_LIMIT = 6;

    public function __construct(private readonly PanelNotificationRepositoryContract $notifications) {}

    public function findOwned(Model $notifiable, string $id): PanelNotificationRecord
    {
        return $this->notifications->findFor($notifiable, $id)
            ?? throw (new ModelNotFoundException)->setModel(PanelNotificationRecord::class, [$id]);
    }

    public function unreadCount(Model $notifiable): int
    {
        return $this->notifications->unreadCount($notifiable);
    }

    public function totalCount(Model $notifiable): int
    {
        return $this->notifications->queryFor($notifiable)->count();
    }

    /**
     * @return array<string, int> category => unread
     */
    public function unreadByCategory(Model $notifiable): array
    {
        $counts = $this->notifications->unreadByCategory($notifiable);
        $result = [];

        foreach (NotificationCategory::cases() as $category) {
            $result[$category->value] = $counts[$category->value] ?? 0;
        }

        return $result;
    }

    public function markRead(PanelNotificationRecord $notification): void
    {
        $notification->markAsRead();
    }

    public function markUnread(PanelNotificationRecord $notification): void
    {
        $notification->markAsUnread();
    }

    public function markAllRead(Model $notifiable): int
    {
        return $this->notifications->markAllRead($notifiable);
    }

    public function delete(PanelNotificationRecord $notification): void
    {
        $this->notifications->delete($notification);
    }

    /**
     * Mark read and return where to go (the notification link, or the inbox).
     */
    public function open(PanelNotificationRecord $notification, string $surface): string
    {
        $this->markRead($notification);

        return $notification->targetUrl() ?? route($surface.'.notifications.index');
    }

    /**
     * Bell dropdown rows (x-ui::notification-dropdown item shape).
     *
     * @return list<array{id: string, title: string, meta: string, time: string, icon: string, tone: string, unread: bool, url: ?string}>
     */
    public function bellItems(Model $notifiable, string $surface): array
    {
        $items = [];

        foreach ($this->notifications->latest($notifiable, self::BELL_LIMIT) as $notification) {
            $items[] = $this->present($notification, $surface);
        }

        return $items;
    }

    /**
     * @return array{id: string, title: string, meta: string, time: string, icon: string, tone: string, unread: bool, url: ?string}
     */
    public function present(PanelNotificationRecord $notification, string $surface): array
    {
        $category = $notification->category();
        $openRoute = $surface.'.notifications.open';

        return [
            'id' => (string) $notification->getKey(),
            'title' => $notification->title(),
            'meta' => $notification->body(),
            'time' => $notification->created_at?->locale(app()->getLocale())->diffForHumans(short: true) ?? '',
            'icon' => $category->icon(),
            'tone' => $category->tone(),
            'unread' => $notification->read_at === null,
            'url' => Route::has($openRoute) ? route($openRoute, $notification->getKey()) : $notification->targetUrl(),
        ];
    }
}
