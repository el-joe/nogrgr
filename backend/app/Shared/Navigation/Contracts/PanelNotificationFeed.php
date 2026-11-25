<?php

namespace App\Shared\Navigation\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Bell data for the panel topbar, provided by the Notifications module (the shared
 * kernel only knows this contract; NullPanelNotificationFeed is the default).
 *
 * @phpstan-type BellFeed array{items: list<array<string, mixed>>, unread: int, markAllUrl: ?string, viewAllUrl: ?string, latestUrl: ?string, realtime: ?array<string, mixed>}
 */
interface PanelNotificationFeed
{
    /**
     * @return BellFeed
     */
    public function for(string $surface, ?Authenticatable $user): array;
}
