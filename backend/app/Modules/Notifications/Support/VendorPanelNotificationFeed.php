<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Services\VendorNotificationInboxService;
use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;

/**
 * Vendor panel bell: latest rows of the store's `notifications` table, unread count,
 * "Mark all read" and the per-tenant private channel `tenant.{tenant}.vendor.{id}`
 * when broadcasting runs on Reverb.
 */
final class VendorPanelNotificationFeed implements PanelNotificationFeed
{
    public function __construct(private readonly VendorNotificationInboxService $inbox) {}

    public function for(string $surface, ?Authenticatable $user): array
    {
        $empty = ['items' => [], 'unread' => 0, 'markAllUrl' => null, 'viewAllUrl' => null, 'latestUrl' => null, 'realtime' => null];

        if (! $user instanceof VendorStaff || ! tenancy()->initialized || ! $user->can('notifications.view')) {
            return $empty;
        }

        return [
            'items' => $this->inbox->bellItems($user),
            'unread' => $this->inbox->unreadCount($user),
            'markAllUrl' => vendor_route('notifications.read-all'),
            'viewAllUrl' => null,
            'latestUrl' => vendor_route('notifications.latest'),
            'realtime' => $this->realtime($user),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function realtime(VendorStaff $user): ?array
    {
        $key = config('broadcasting.connections.reverb.key');

        if (config('broadcasting.default') !== 'reverb' || ! is_string($key) || $key === '') {
            return null;
        }

        $scheme = (string) config('broadcasting.connections.reverb.options.scheme', 'https');
        $icons = [];

        foreach (NotificationCategory::cases() as $category) {
            $icons[$category->icon()] = Blade::render('<x-ui::icon :name="$name" :size="18" />', ['name' => $category->icon()]);
        }

        return [
            'key' => $key,
            'host' => (string) (config('broadcasting.connections.reverb.options.host') ?: request()->getHost()),
            'port' => (int) config('broadcasting.connections.reverb.options.port', $scheme === 'https' ? 443 : 80),
            'tls' => $scheme === 'https',
            'channel' => 'private-'.$user->receivesBroadcastNotificationsOn(),
            'authUrl' => vendor_route('notifications.broadcast-auth'),
            'event' => 'panel.notification',
            'icons' => $icons,
        ];
    }
}
