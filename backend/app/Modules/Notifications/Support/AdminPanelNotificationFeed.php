<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Services\NotificationInboxService;
use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Topbar bell for central panels: latest rows, unread count, "Mark all read" / "View
 * all" links and — when broadcasting runs on Reverb — the private channel the browser
 * subscribes to (resources/js/panels/notification-live.js).
 */
final class AdminPanelNotificationFeed implements PanelNotificationFeed
{
    public function __construct(private readonly NotificationInboxService $inbox) {}

    public function for(string $surface, ?Authenticatable $user): array
    {
        $empty = ['items' => [], 'unread' => 0, 'markAllUrl' => null, 'viewAllUrl' => null, 'latestUrl' => null, 'realtime' => null];

        if (! $user instanceof Model || ! $user instanceof HasPanelInbox || $user->panelSurface() !== $surface
            || ! Route::has($surface.'.notifications.index') || ! Gate::forUser($user)->allows('notifications.view')) {
            return $empty;
        }

        return [
            'items' => $this->inbox->bellItems($user, $surface),
            'unread' => $this->inbox->unreadCount($user),
            'markAllUrl' => route($surface.'.notifications.read-all'),
            'viewAllUrl' => route($surface.'.notifications.index'),
            'latestUrl' => route($surface.'.notifications.latest'),
            'realtime' => $this->realtime($user, $surface),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function realtime(HasPanelInbox $user, string $surface): ?array
    {
        $key = config('broadcasting.connections.reverb.key');

        if (config('broadcasting.default') !== 'reverb' || ! is_string($key) || $key === '' || ! Route::has($surface.'.notifications.broadcast-auth')) {
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
            'channel' => 'private-'.$user->panelChannel(),
            'authUrl' => route($surface.'.notifications.broadcast-auth'),
            'event' => 'panel.notification',
            'icons' => $icons,
        ];
    }
}
