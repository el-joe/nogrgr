<?php

namespace App\Shared\Navigation;

use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Empty bell for surfaces whose notifications are not wired.
 */
final class NullPanelNotificationFeed implements PanelNotificationFeed
{
    public function for(string $surface, ?Authenticatable $user): array
    {
        return ['items' => [], 'unread' => 0, 'markAllUrl' => null, 'viewAllUrl' => null, 'latestUrl' => null, 'realtime' => null];
    }
}
