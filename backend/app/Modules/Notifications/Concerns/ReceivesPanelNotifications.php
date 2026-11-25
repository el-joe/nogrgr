<?php

namespace App\Modules\Notifications\Concerns;

use App\Modules\Notifications\Models\PanelNotificationRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Inbox rows on the central DB and the private channel "<surface>.<id>" for live
 * notifications (routes/channels registered by the Notifications module).
 *
 * @phpstan-require-extends Model
 */
trait ReceivesPanelNotifications
{
    /**
     * @return MorphMany<PanelNotificationRecord, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(PanelNotificationRecord::class, 'notifiable')->latest();
    }

    public function panelChannel(): string
    {
        return $this->panelSurface().'.'.$this->getKey();
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return $this->panelChannel();
    }
}
