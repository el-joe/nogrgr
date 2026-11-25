<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Repositories\Contracts\TenantNotificationRepositoryContract;
use App\Shared\Routing\VendorPanel;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;

/**
 * Vendor panel bell (VEN-006) and inbox (VEN-090): the signed-in staff member's rows in the store's own
 * `notifications` table. URLs go through vendor_route() so both host modes work.
 */
class VendorNotificationInboxService extends BaseService
{
    public function __construct(private readonly TenantNotificationRepositoryContract $notifications) {}

    public function findOwned(Model $notifiable, string $id): PanelNotificationRecord
    {
        return $this->notifications->findFor($notifiable, $id)
            ?? throw (new ModelNotFoundException)->setModel(PanelNotificationRecord::class, [$id]);
    }

    public function unreadCount(Model $notifiable): int
    {
        return $this->notifications->unreadCount($notifiable);
    }

    public function markAllRead(Model $notifiable): int
    {
        return $this->notifications->markAllRead($notifiable);
    }

    /**
     * Marks read and returns where to go: the vendor route stored on the row (resolved in
     * the current host mode), the notification link, or the dashboard.
     */
    public function open(PanelNotificationRecord $notification): string
    {
        $notification->markAsRead();

        return $this->vendorTarget($notification) ?? $notification->targetUrl() ?? vendor_route('dashboard');
    }

    public function markRead(PanelNotificationRecord $notification): void
    {
        $notification->markAsRead();
    }

    public function markUnread(PanelNotificationRecord $notification): void
    {
        $notification->markAsUnread();
    }

    /**
     * @return Builder<PanelNotificationRecord>
     */
    public function query(Model $notifiable): Builder
    {
        return $this->notifications->queryFor($notifiable);
    }

    private function vendorTarget(PanelNotificationRecord $notification): ?string
    {
        $target = $notification->data['vendor_route'] ?? null;
        $name = is_array($target) ? ($target['name'] ?? null) : null;

        if (! is_string($name) || $name === '' || ! Route::has(VendorPanel::TENANT_NAME_PREFIX.$name)) {
            return null;
        }

        $parameters = is_array($target['parameters'] ?? null) ? $target['parameters'] : [];

        return vendor_route($name, $parameters);
    }

    /**
     * @return list<array{id: string, title: string, meta: string, time: string, icon: string, tone: string, unread: bool, url: ?string}>
     */
    public function bellItems(Model $notifiable): array
    {
        $items = [];

        foreach ($this->notifications->latest($notifiable, NotificationInboxService::BELL_LIMIT) as $notification) {
            $category = $notification->category();
            $items[] = [
                'id' => (string) $notification->getKey(),
                'title' => $notification->title(),
                'meta' => $notification->body(),
                'time' => $notification->created_at?->locale(app()->getLocale())->diffForHumans(short: true) ?? '',
                'icon' => $category->icon(),
                'tone' => $category->tone(),
                'unread' => $notification->read_at === null,
                'url' => vendor_route('notifications.open', ['notification' => $notification->getKey()]),
            ];
        }

        return $items;
    }
}
