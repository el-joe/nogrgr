<?php

namespace App\Modules\Notifications\Support;

use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Routes the topbar bell to the right inbox: the store's own table for the vendor
 * panel, the central table for admin / affiliate.
 */
final class SurfacePanelNotificationFeed implements PanelNotificationFeed
{
    public function __construct(
        private readonly AdminPanelNotificationFeed $central,
        private readonly VendorPanelNotificationFeed $vendor,
    ) {}

    public function for(string $surface, ?Authenticatable $user): array
    {
        return $surface === 'vendor' ? $this->vendor->for($surface, $user) : $this->central->for($surface, $user);
    }
}
