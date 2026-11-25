<?php

namespace App\Modules\Notifications\Contracts;

/**
 * A central panel user (admin, affiliate) with an inbox, preferences and a live
 * Reverb channel. Implemented through the ReceivesPanelNotifications trait.
 */
interface HasPanelInbox
{
    /**
     * Panel surface whose routes serve the inbox ("admin", "affiliate").
     */
    public function panelSurface(): string;

    /**
     * Private broadcast channel name without the "private-" prefix.
     */
    public function panelChannel(): string;
}
